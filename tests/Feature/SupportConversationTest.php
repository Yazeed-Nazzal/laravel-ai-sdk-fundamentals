<?php

use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    Http::preventStrayRequests();
    config(['ai.providers.openai.key' => 'test', 'ai.providers.openai.url' => 'https://api.openai.com/v1']);
});
function supportReply(string $id, string $text = 'Order reply'): array
{
    return ['id' => $id, 'status' => 'completed', 'output' => [
        ['type' => 'message', 'status' => 'completed', 'content' => [['type' => 'output_text', 'text' => $text]]],
    ]];
}
test('starts and resumes an owned conversation using server stored response IDs', function () {
    $user = User::factory()->create();
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(supportReply('resp_first'))->push(supportReply('resp_second'))]);

    $first = $this->actingAs($user)->postJson(route('support-conversations.store'), ['message' => 'My order?'])
        ->assertOk();
    $id = $first->json('conversation_id');
    $this->postJson(route('support-conversations.store'), ['message' => 'Order 8', 'conversation_id' => $id])->assertOk();

    $requests = Http::recorded();
    expect($requests[0][0]->data())->not->toHaveKey('previous_response_id');
    expect($requests[1][0]['previous_response_id'])->toBe('resp_first');
    $this->assertDatabaseHas('support_conversations', ['id' => $id, 'user_id' => $user->id, 'last_response_id' => 'resp_second']);
    $this->assertDatabaseCount('support_messages', 4);
    $this->getJson(route('support-conversations.show', $id))->assertOk()->assertJsonCount(4, 'messages');
});
test('denies access to another users conversation without contacting OpenAI', function () {
    $user = User::factory()->create();
    $conversation = SupportConversation::factory()->create();

    $this->actingAs($user)->getJson(route('support-conversations.show', $conversation->id))->assertNotFound();
    $this->postJson(route('support-conversations.store'), ['message' => 'Hi', 'conversation_id' => $conversation->id])->assertNotFound();
    $this->getJson(route('support-conversations.index'))->assertOk()->assertExactJson(['conversations' => []]);

    Http::assertNothingSent();
});
test('rejects nonexistent conversations', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('support-conversations.store'), ['message' => 'Hi', 'conversation_id' => 99999])->assertNotFound();
    Http::assertNothingSent();
});
test('rejects untrusted continuation and identity fields', function (string $field) {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('support-conversations.store'), ['message' => 'Hi', $field => 'spoofed'])->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with(['previous_response_id', 'last_response_id', 'user_id']);
test('preserves the prior response and history on an incomplete turn', function () {
    $user = User::factory()->create();
    $conversation = SupportConversation::factory()->create(['user_id' => $user->id, 'last_response_id' => 'resp_prior']);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_partial', 'status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'], 'output' => [],
    ])]);

    $this->actingAs($user)->postJson(route('support-conversations.store'), ['message' => 'Hi', 'conversation_id' => $conversation->id])->assertStatus(502);

    expect($conversation->fresh()->last_response_id)->toBe('resp_prior');
    $this->assertDatabaseCount('support_messages', 0);
});
test('prevents overlapping turns for the same conversation', function () {
    $user = User::factory()->create();
    $conversation = SupportConversation::factory()->create(['user_id' => $user->id]);
    $lock = Cache::lock('support-conversation:'.$conversation->id, 180);
    $lock->get();
    try {
        $this->actingAs($user)->postJson(route('support-conversations.store'), ['message' => 'Hi', 'conversation_id' => $conversation->id])->assertStatus(409);
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});
test('uses the tool call response ID after resuming instead of overriding it with the old response', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user, 'customer')->create(['status' => 'shipped']);
    $conversation = SupportConversation::factory()->create(['user_id' => $user->id, 'last_response_id' => 'resp_prior']);
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()->push([
        'id' => 'resp_tool', 'status' => 'completed', 'output' => [
            ['type' => 'function_call', 'status' => 'completed', 'call_id' => 'call_status', 'name' => 'GetOrderStatus', 'arguments' => json_encode(['order_id' => $order->id])],
        ],
    ])->push(supportReply('resp_final'))]);

    $this->actingAs($user)->postJson(route('support-conversations.store'), ['message' => 'Check my order', 'conversation_id' => $conversation->id])->assertOk();

    $requests = Http::recorded();
    expect($requests[0][0]['previous_response_id'])->toBe('resp_prior');
    expect($requests[1][0]['previous_response_id'])->toBe('resp_tool');
    expect($requests[1][0]['input'][0]['output'])->toBe(json_encode(['order_id' => $order->id, 'status' => 'shipped']));
    expect($conversation->fresh()->last_response_id)->toBe('resp_final');
});
test('requires authentication for every conversation endpoint', function () {
    $this->getJson(route('support-conversations.index'))->assertUnauthorized();
    $this->getJson(route('support-conversations.show', 1))->assertUnauthorized();
    $this->postJson(route('support-conversations.store'), ['message' => 'Hi'])->assertUnauthorized();
    Http::assertNothingSent();
});
