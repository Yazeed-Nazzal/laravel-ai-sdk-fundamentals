<?php

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'gpt-6-luna']);
});

function manualConversationReply(string $id): array
{
    return ['id' => $id, 'status' => 'completed', 'output' => [
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Please provide the order ID.']]],
    ]];
}

test('protects the manual page and all conversation endpoints', function () {
    $this->get(route('manual-chat.show'))->assertRedirect(route('login'));
    $this->getJson(route('manual-conversations.index'))->assertUnauthorized();
    $this->getJson(route('manual-conversations.show', 1))->assertUnauthorized();
    $this->postJson(route('manual-conversations.store'), ['message' => 'Hello'])->assertUnauthorized();

    Http::assertNothingSent();
});

test('renders the manual chat with its own endpoint URLs', function () {
    $this->actingAs(User::factory()->create())->get(route('manual-chat.show'))
        ->assertOk()->assertSee('Manual agent')->assertSee(route('manual-conversations.store'));
    Http::assertNothingSent();
});

test('persists manual turns and continues with the server stored final response ID', function () {
    $user = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(manualConversationReply('resp_first'))->push(manualConversationReply('resp_second'))]);

    $first = $this->actingAs($user)->postJson(route('manual-conversations.store'), ['message' => 'My order?'])->assertOk();
    $id = $first->json('conversation_id');
    $this->postJson(route('manual-conversations.store'), ['message' => 'Order 8', 'conversation_id' => $id])->assertOk();

    expect(Http::recorded()[1][0]['previous_response_id'])->toBe('resp_first');
    $this->assertDatabaseHas('support_conversations', ['id' => $id, 'user_id' => $user->id, 'agent_driver' => 'manual', 'last_response_id' => 'resp_second']);
    $this->assertDatabaseCount('support_messages', 4);
    $this->getJson(route('manual-conversations.show', $id))->assertOk()->assertJsonCount(4, 'messages');
    $this->getJson(route('manual-conversations.index'))->assertOk()->assertJsonCount(1, 'conversations');
    $this->getJson(route('support-conversations.index'))->assertOk()->assertExactJson(['conversations' => []]);
});

test('rejects another customers manual conversation', function () {
    $conversation = SupportConversation::factory()->create(['agent_driver' => 'manual']);
    $this->actingAs(User::factory()->create())->getJson(route('manual-conversations.show', $conversation->id))->assertNotFound();
    $this->postJson(route('manual-conversations.store'), ['message' => 'Hi', 'conversation_id' => $conversation->id])->assertNotFound();
    Http::assertNothingSent();
});

test('isolates SDK and manual conversation IDs in both directions', function () {
    $user = User::factory()->create();
    $sdk = SupportConversation::factory()->create(['user_id' => $user->id]);
    $manual = SupportConversation::factory()->create(['user_id' => $user->id, 'agent_driver' => 'manual']);

    $this->actingAs($user)->postJson(route('manual-conversations.store'), ['message' => 'Hi', 'conversation_id' => $sdk->id])->assertNotFound();
    $this->postJson(route('support-conversations.store'), ['message' => 'Hi', 'conversation_id' => $manual->id])->assertNotFound();
    $this->getJson(route('manual-conversations.show', $sdk->id))->assertNotFound();
    $this->getJson(route('support-conversations.show', $manual->id))->assertNotFound();
    Http::assertNothingSent();
});

test('does not advance persisted state when the manual turn fails', function (string $failure, int $status) {
    $user = User::factory()->create();
    $conversation = SupportConversation::factory()->create(['user_id' => $user->id, 'agent_driver' => 'manual', 'last_response_id' => 'resp_previous']);
    $fake = match ($failure) {
        'rate limit' => Http::response(['error' => ['message' => 'Private']], 429),
        'server error' => Http::response(['error' => ['message' => 'Private']], 500),
        'connection' => Http::failedConnection(),
        'incomplete' => Http::response(array_replace(manualConversationReply('resp_incomplete'), ['status' => 'incomplete'])),
    };
    Http::fake(['https://api.openai.com/v1/responses' => $fake]);

    $this->actingAs($user)->postJson(route('manual-conversations.store'), ['message' => 'Hi', 'conversation_id' => $conversation->id])
        ->assertStatus($status)->assertJsonPath('conversation_id', $conversation->id);

    expect($conversation->fresh()->last_response_id)->toBe('resp_previous');
    $this->assertDatabaseCount('support_messages', 0);
    Http::assertSentCount(1);
})->with(['rate limit' => ['rate limit', 429], 'server error' => ['server error', 503], 'connection' => ['connection', 503], 'incomplete' => ['incomplete', 502]]);

test('rejects identity and continuation spoofing before making a provider request', function (string $field) {
    $this->actingAs(User::factory()->create())->postJson(route('manual-conversations.store'), ['message' => 'Hi', $field => 'spoofed'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with(['user_id', 'previous_response_id', 'last_response_id']);

test('honors the existing conversation lock', function () {
    $user = User::factory()->create();
    $conversation = SupportConversation::factory()->create(['user_id' => $user->id, 'agent_driver' => 'manual']);
    $lock = Cache::lock('support-conversation:'.$conversation->id, 180);
    $lock->get();
    try {
        $this->actingAs($user)->postJson(route('manual-conversations.store'), ['message' => 'Hi', 'conversation_id' => $conversation->id])->assertStatus(409);
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});

test('prevents a stale turn from overwriting a newer conversation head', function () {
    $user = User::factory()->create();
    $conversation = SupportConversation::factory()->create(['user_id' => $user->id, 'agent_driver' => 'manual', 'last_response_id' => 'resp_prior']);
    Http::fake(['https://api.openai.com/v1/responses' => function () use ($conversation) {
        $conversation->update(['last_response_id' => 'resp_newer']);

        return Http::response(manualConversationReply('resp_stale'));
    }]);

    $this->actingAs($user)->postJson(route('manual-conversations.store'), ['message' => 'Hi', 'conversation_id' => $conversation->id])->assertStatus(409);

    expect($conversation->fresh()->last_response_id)->toBe('resp_newer');
    $this->assertDatabaseCount('support_messages', 0);
});
