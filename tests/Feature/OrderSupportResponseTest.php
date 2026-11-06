<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'ai.default' => 'openai',
        'ai.providers.openai.key' => 'test-key',
        'ai.providers.openai.url' => 'https://api.openai.com/v1',
    ]);
});

test('returns 200 with a completed answer', function () {
    $customer = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_success', 'status' => 'completed', 'model' => 'gpt-6-luna',
        'output' => [['type' => 'message', 'status' => 'completed', 'content' => [
            ['type' => 'output_text', 'text' => 'Please provide your order ID.'],
        ]]],
    ], 200)]);

    $this->actingAs($customer)->postJson(route('order-support'), ['message' => 'Where is my order?'])
        ->assertOk()->assertExactJson(['message' => 'Please provide your order ID.']);

    Http::assertSentCount(1);
});

test('returns 502 rather than presenting a partial answer as complete', function (string $reason) {
    $customer = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_incomplete', 'status' => 'incomplete', 'model' => 'gpt-6-luna',
        'incomplete_details' => ['reason' => $reason],
        'output' => [['type' => 'message', 'status' => 'incomplete', 'content' => [
            ['type' => 'output_text', 'text' => 'Partial answer that must not be displayed'],
        ]]],
    ], 200)]);

    $this->actingAs($customer)->postJson(route('order-support'), ['message' => 'Where is my order?'])
        ->assertStatus(502)->assertExactJson(['message' => 'The assistant could not complete its answer. Please try again.']);

    Http::assertSentCount(1);
})->with(['max_output_tokens', 'content_filter', 'unknown']);

test('returns 429 when OpenAI rate limits the agent', function () {
    $customer = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'error' => ['type' => 'rate_limit_error', 'message' => 'Private upstream details'],
    ], 429)]);

    $this->actingAs($customer)->postJson(route('order-support'), ['message' => 'Where is my order?'])
        ->assertTooManyRequests()->assertExactJson(['message' => 'The assistant is busy. Please try again later.']);

    Http::assertSentCount(1);
});

test('returns 502 when a completed response has no answer text', function () {
    $customer = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_empty', 'status' => 'completed',
        'output' => [['type' => 'message', 'status' => 'completed', 'content' => []]],
    ])]);

    $this->actingAs($customer)->postJson(route('order-support'), ['message' => 'Where is my order?'])
        ->assertStatus(502)->assertExactJson(['message' => 'The assistant could not complete its answer. Please try again.']);

    Http::assertSentCount(1);
});

test('returns 503 when the provider is unavailable', function (string $failure) {
    $customer = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => $failure === 'connection'
        ? Http::failedConnection()
        : Http::response(['error' => ['message' => 'Private provider error']], 500)]);

    $this->actingAs($customer)->postJson(route('order-support'), ['message' => 'Where is my order?'])
        ->assertServiceUnavailable()->assertExactJson(['message' => 'The assistant is unavailable. Please try again.']);

    Http::assertSentCount(1);
})->with(['connection', 'server']);
