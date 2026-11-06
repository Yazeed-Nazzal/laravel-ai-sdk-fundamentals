<?php

use App\Ai\Agents\OrderSupportAgent;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\OrderSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Tools\Request;

uses(LazilyRefreshDatabase::class);

test('returns 401 for guests without invoking the agent', function () {
    OrderSupportAgent::fake()->preventStrayPrompts();

    $this->postJson(route('order-support'), ['message' => 'Where is my order?'])->assertUnauthorized();

    OrderSupportAgent::assertNeverPrompted();
});

test('binds the authenticated customer even when the HTTP payload spoofs identity', function () {
    $customer = User::factory()->create();
    $otherOrder = Order::factory()->create();
    OrderSupportAgent::fake(['Order not found.'])->preventStrayPrompts();

    $this->actingAs($customer)->postJson(route('order-support'), [
        'message' => 'Check my order',
        'customer_id' => $otherOrder->user_id,
    ])->assertOk()->assertExactJson(['message' => 'Order not found.']);

    OrderSupportAgent::assertPrompted(function (AgentPrompt $prompt) use ($otherOrder): bool {
        $result = $prompt->agent->tools()[0]->handle(new Request(['order_id' => $otherOrder->id]));

        return $prompt->prompt === 'Check my order' && $result === '{"error":"order_not_found"}';
    });
});

test('returns 422 for invalid messages before invoking the agent', function (array $payload) {
    $customer = User::factory()->create();
    OrderSupportAgent::fake()->preventStrayPrompts();

    $this->actingAs($customer)->postJson(route('order-support'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('message');

    OrderSupportAgent::assertNeverPrompted();
})->with([
    'missing' => [[]],
    'array' => [['message' => []]],
    'too long' => [['message' => str_repeat('a', 2001)]],
]);

test('seeds two sandbox customers and six orders without duplicating them', function () {
    $this->seed(OrderSeeder::class);
    $this->seed(OrderSeeder::class);

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('orders', 6);
    $this->assertDatabaseHas('users', ['email' => 'alice@example.test']);
    expect(Order::query()->where('status', 'shipped')->count())->toBe(2);
});

test('shows the chat form to an authenticated customer', function () {
    $customer = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($customer)->get(route('order-support.show'))
        ->assertOk()
        ->assertViewIs('order-support')
        ->assertSee('Your question')
        ->assertSee('Assistant response')
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('redirects local guests to login before showing order support', function () {
    $this->app->instance('env', 'local');

    $this->get(route('order-support.show'))->assertRedirect(route('login'));
});

test('does not allow the old sandbox shortcut to authenticate a customer', function () {
    $this->app->instance('env', 'local');
    User::factory()->create(['email' => 'alice@example.test']);

    $this->withSession(['_token' => 'sandbox-test-token'])->post('/order-support/sandbox-login', [
        '_token' => 'sandbox-test-token',
    ])->assertNotFound();

    $this->assertGuest();
});

test('sandbox shortcut remains unavailable in production', function () {
    $this->app->instance('env', 'production');

    $this->withSession(['_token' => 'sandbox-test-token'])->post('/order-support/sandbox-login', [
        '_token' => 'sandbox-test-token',
    ])->assertNotFound();

    $this->assertGuest();
});

test('redirects production guests to login before showing order support', function () {
    $this->app->instance('env', 'production');

    $this->get(route('order-support.show'))->assertRedirect(route('login'));
});
