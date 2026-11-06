<?php

use App\Ai\Agents\OrderSupportAgent;
use App\Ai\Tools\GetOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(LazilyRefreshDatabase::class);

test('returns only status and order ID for an authorized order without changing it', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->for($customer, 'customer')->create(['status' => 'shipped']);
    $before = $order->fresh()->getAttributes();
    $tools = (new OrderSupportAgent($customer, app(OrderService::class)))->tools();

    $result = $tools[0]->handle(new Request(['order_id' => $order->id]));

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(GetOrderStatus::class);
    expect(json_decode($result, true, flags: JSON_THROW_ON_ERROR))->toBe(['order_id' => $order->id, 'status' => 'shipped']);
    expect($order->refresh()->getAttributes())->toBe($before);
});

test('conceals another customers order', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->create();
    $tool = new GetOrderStatus(app(OrderService::class), $customer->id);

    $result = $tool->handle(new Request(['order_id' => $order->id]));

    expect($result)->toBe('{"error":"order_not_found"}');
});

test('returns the same error for a nonexistent order', function () {
    $customer = User::factory()->create();
    $tool = new GetOrderStatus(app(OrderService::class), $customer->id);

    $result = $tool->handle(new Request(['order_id' => 999999]));

    expect($result)->toBe('{"error":"order_not_found"}');
});

test('rejects malformed arguments before querying orders', function (array $arguments) {
    $tool = new GetOrderStatus(app(OrderService::class), 1);
    $this->expectsDatabaseQueryCount(0);

    $result = $tool->handle(new Request($arguments));

    expect($result)->toBe('{"error":"invalid_arguments"}');
})->with([
    'missing' => [[]],
    'null' => [['order_id' => null]],
    'string' => [['order_id' => '1']],
    'boolean' => [['order_id' => true]],
    'float' => [['order_id' => 1.5]],
    'zero' => [['order_id' => 0]],
    'negative' => [['order_id' => -1]],
    'array' => [['order_id' => [1]]],
    'sql injection' => [['order_id' => '1 OR 1=1']],
    'overflow' => [['order_id' => '9223372036854775808']],
    'spoofed identity' => [['order_id' => 1, 'customer_id' => 2]],
    'unknown key' => [['order_id' => 1, 'include' => 'customer']],
]);

test('cannot override customer ownership using tool arguments', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->create();
    $tool = (new OrderSupportAgent($customer, app(OrderService::class)))->tools()[0];

    $result = $tool->handle(new Request(['order_id' => $order->id, 'customer_id' => $order->user_id]));

    expect($result)->toBe('{"error":"invalid_arguments"}');
});
