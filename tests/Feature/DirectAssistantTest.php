<?php

use App\Models\User;
use App\Services\AI\OpenAIResponseService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'test-model']);
});

test('protects the direct assistant page and endpoint', function () {
    $this->get(route('direct-assistant.show'))->assertRedirect(route('login'));
    $this->postJson(route('direct-assistant'), ['message' => 'Hello'])->assertUnauthorized();

    Http::assertNothingSent();
});

test('shows the direct assistant to authenticated users', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get(route('direct-assistant.show'))
        ->assertOk()->assertViewIs('direct-assistant')->assertSee('Direct assistant');
});

test('sends a direct request and extracts message text while ignoring other output', function () {
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_test', 'status' => 'completed',
        'output' => [
            ['type' => 'reasoning', 'content' => []],
            ['type' => 'message', 'content' => [
                ['type' => 'output_text', 'text' => 'First'],
                ['type' => 'refusal', 'refusal' => 'Ignored'],
                ['type' => 'output_text', 'text' => 'Second'],
            ]],
        ],
        'usage' => ['total_tokens' => 20],
    ])]);

    $result = app(OpenAIResponseService::class)->ask('Hello');

    expect($result)->toBe(['response_id' => 'resp_test', 'answer' => "First\nSecond", 'usage' => ['total_tokens' => 20]]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->data() === [
            'model' => 'test-model',
            'instructions' => 'You are an Arabic customer support assistant.',
            'input' => 'Hello',
            'max_output_tokens' => 300,
        ]);
});

test('returns the direct response to the page', function () {
    $customer = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_test', 'status' => 'completed',
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hello customer']]]],
    ])]);

    $this->actingAs($customer)->postJson(route('direct-assistant'), ['message' => 'Hello'])
        ->assertOk()->assertExactJson(['message' => 'Hello customer']);

    Http::assertSentCount(1);
});

test('rejects malformed messages before calling OpenAI', function (array $payload) {
    $customer = User::factory()->create();

    $this->actingAs($customer)->postJson(route('direct-assistant'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('message');

    Http::assertNothingSent();
})->with([
    'missing' => [[]],
    'array' => [['message' => []]],
    'too long' => [['message' => str_repeat('x', 2001)]],
]);

test('returns a safe error when the provider cannot provide an answer', function (string $failure) {
    $customer = User::factory()->create();
    $fake = match ($failure) {
        'connection' => Http::failedConnection(),
        'http' => Http::response(['error' => 'private provider error'], 500),
        'incomplete' => Http::response(['status' => 'incomplete']),
        'no text' => Http::response(['id' => 'resp_test', 'status' => 'completed', 'output' => []]),
    };
    Http::fake(['https://api.openai.com/v1/responses' => $fake]);

    $this->actingAs($customer)->postJson(route('direct-assistant'), ['message' => 'Hello'])
        ->assertServiceUnavailable()->assertExactJson(['message' => 'The assistant is unavailable. Please try again.']);

    Http::assertSentCount(1);
})->with(['connection', 'http', 'incomplete', 'no text']);
