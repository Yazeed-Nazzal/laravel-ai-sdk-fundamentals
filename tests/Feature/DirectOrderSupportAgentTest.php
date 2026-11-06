<?php

use App\Models\Order;
use App\Models\User;
use App\Services\AI\AgentException;
use App\Services\AI\Data\OpenAIResponseRequest;
use App\Services\AI\DirectOrderSupportAgent;
use App\Services\AI\OpenAIResponseService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'gpt-6-luna',
        'services.openai.agent_max_rounds' => 4, 'services.openai.agent_max_tool_calls' => 6]);
});

function manualTextResponse(string $id = 'resp_final', string $text = 'Your order is shipped.'): array
{
    return ['id' => $id, 'status' => 'completed', 'output' => [
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]],
    ], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15,
        'input_tokens_details' => ['cached_tokens' => 2]]];
}

function manualFunctionCall(string $id, string $name, string $arguments): array
{
    return ['type' => 'function_call', 'call_id' => $id, 'name' => $name, 'arguments' => $arguments, 'status' => 'completed'];
}

function manualCallsResponse(array $calls, string $id = 'resp_tools'): array
{
    return ['id' => $id, 'status' => 'completed', 'output' => $calls,
        'usage' => ['input_tokens' => 20, 'output_tokens' => 10, 'total_tokens' => 30,
            'input_tokens_details' => ['cached_tokens' => 3]]];
}

test('returns normalized final text and usage without executing tools', function () {
    $customer = User::factory()->make();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(manualTextResponse())]);

    $result = app(DirectOrderSupportAgent::class)->respond($customer, 'Where is my order?', null);

    expect($result->text)->toBe('Your order is shipped.');
    expect($result->finalResponseId)->toBe('resp_final');
    expect($result->rounds)->toBe(1);
    expect($result->toolCallCount)->toBe(0);
    expect($result->usage['total_tokens'])->toBe(15);
    Http::assertSent(fn ($request) => $request['model'] === 'gpt-6-luna'
        && $request['tools'][0]['name'] === 'get_order_status'
        && $request['tools'][0]['parameters']['additionalProperties'] === false
        && ! isset($request['previous_response_id']));
});

test('executes an authorized order lookup and chains its output to the tool response', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->for($customer, 'customer')->create(['status' => 'shipped']);
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(manualCallsResponse([manualFunctionCall('call_order', 'get_order_status', json_encode(['order_id' => $order->id]))]))
        ->push(manualTextResponse())]);

    $result = app(DirectOrderSupportAgent::class)->respond($customer, 'Check it', 'resp_previous');

    $requests = Http::recorded();
    expect($requests[0][0]['previous_response_id'])->toBe('resp_previous');
    expect($requests[1][0]['previous_response_id'])->toBe('resp_tools');
    expect($requests[1][0]['input'])->toBe([[
        'type' => 'function_call_output', 'call_id' => 'call_order',
        'output' => json_encode(['order_id' => $order->id, 'status' => 'shipped']),
    ]]);
    expect($requests[1][0]['instructions'])->toBe($requests[0][0]['instructions']);
    expect($result->rounds)->toBe(2);
    expect($result->toolCallCount)->toBe(1);
    expect($result->usage)->toBe([
        'input_tokens' => 30, 'output_tokens' => 15, 'total_tokens' => 45,
        'input_tokens_details' => ['cached_tokens' => 5],
    ]);
    expect($order->fresh()->status)->toBe('shipped');
});

test('supports multiple tool calls and preserves each call ID', function () {
    $customer = User::factory()->create();
    $first = Order::factory()->for($customer, 'customer')->create(['status' => 'pending']);
    $second = Order::factory()->for($customer, 'customer')->create(['status' => 'delivered']);
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(manualCallsResponse([
            manualFunctionCall('call_first', 'get_order_status', json_encode(['order_id' => $first->id])),
            manualFunctionCall('call_second', 'get_order_status', json_encode(['order_id' => $second->id])),
        ]))->push(manualTextResponse())]);

    $result = app(DirectOrderSupportAgent::class)->respond($customer, 'Check both', null);

    $outputs = Http::recorded()[1][0]['input'];
    expect(array_column($outputs, 'call_id'))->toBe(['call_first', 'call_second']);
    expect(json_decode($outputs[0]['output'], true))->toBe(['order_id' => $first->id, 'status' => 'pending']);
    expect(json_decode($outputs[1]['output'], true))->toBe(['order_id' => $second->id, 'status' => 'delivered']);
    expect($result->toolCallCount)->toBe(2);
});

test('returns safe tool errors for unknown names and invalid arguments', function (string $name, string $arguments, string $error) {
    $customer = User::factory()->make();
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(manualCallsResponse([manualFunctionCall('call_bad', $name, $arguments)]))
        ->push(manualTextResponse())]);

    app(DirectOrderSupportAgent::class)->respond($customer, 'Check', null);

    expect(Http::recorded()[1][0]['input'][0])->toBe([
        'type' => 'function_call_output', 'call_id' => 'call_bad', 'output' => json_encode(['error' => $error]),
    ]);
})->with([
    'arbitrary class' => ['App\\Models\\User::all', '{}', 'unknown_tool'],
    'write tool' => ['cancel_order', '{"order_id":1}', 'unknown_tool'],
    'invalid json' => ['get_order_status', '{', 'invalid_arguments'],
    'array' => ['get_order_status', '[1]', 'invalid_arguments'],
    'null' => ['get_order_status', 'null', 'invalid_arguments'],
    'missing id' => ['get_order_status', '{}', 'invalid_arguments'],
    'string id' => ['get_order_status', '{"order_id":"1"}', 'invalid_arguments'],
    'negative' => ['get_order_status', '{"order_id":-1}', 'invalid_arguments'],
    'spoofed identity' => ['get_order_status', '{"order_id":1,"customer_id":2}', 'invalid_arguments'],
]);

test('does not reveal whether an inaccessible order exists', function (bool $exists) {
    $customer = User::factory()->create();
    $id = $exists ? Order::factory()->create()->id : 99999;
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(manualCallsResponse([manualFunctionCall('call_private', 'get_order_status', json_encode(['order_id' => $id]))]))
        ->push(manualTextResponse())]);

    app(DirectOrderSupportAgent::class)->respond($customer, 'Check', null);

    expect(Http::recorded()[1][0]['input'][0]['output'])->toBe('{"error":"order_not_found"}');
})->with(['other customer' => true, 'nonexistent' => false]);

test('maps provider HTTP failures without retrying', function (int $providerStatus, int $expectedStatus) {
    $customer = User::factory()->make();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Private']], $providerStatus)]);

    try {
        app(DirectOrderSupportAgent::class)->respond($customer, 'Check', null);
        $this->fail('Expected provider failure');
    } catch (AgentException $exception) {
        expect($exception->status)->toBe($expectedStatus);
    }
    Http::assertSentCount(1);
})->with(['rate limit' => [429, 429], 'server error' => [500, 503], 'invalid credentials' => [401, 503]]);

test('handles connection timeouts', function () {
    Http::fake(['https://api.openai.com/v1/responses' => Http::failedConnection()]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Check', null))
        ->toThrow(AgentException::class);
    Http::assertSentCount(1);
});

test('rejects incomplete failed and unsupported provider states', function (string $status) {
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(array_replace(manualTextResponse(), ['status' => $status]))]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Check', null))
        ->toThrow(AgentException::class);
    Http::assertSentCount(1);
})->with(['incomplete', 'failed', 'queued', 'in_progress', 'cancelled']);

test('rejects malformed response envelopes', function (array $changes) {
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(array_replace(manualTextResponse(), $changes))]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Check', null))
        ->toThrow(AgentException::class);
})->with([
    'missing id' => [['id' => null]],
    'integer id' => [['id' => 3]],
    'empty id' => [['id' => '']],
    'malformed id' => [['id' => 'not-a-response']],
    'missing output' => [['output' => null]],
    'wrong output' => [['output' => 'invalid']],
    'bad item' => [['output' => [null]]],
    'empty text' => [['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => ' ']]]]]],
]);

test('rejects invalid JSON responses', function () {
    Http::fake(['https://api.openai.com/v1/responses' => Http::response('not json')]);

    expect(fn () => app(OpenAIResponseService::class)->createResponse(new OpenAIResponseRequest('gpt-6-luna', 'Support', 'Hi')))
        ->toThrow(AgentException::class);
});

test('rejects invalid and duplicate call IDs before dispatching', function (array $calls) {
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(manualCallsResponse($calls))]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Check', null))
        ->toThrow(AgentException::class);
    Http::assertSentCount(1);
})->with([
    'missing call id' => [[['type' => 'function_call', 'name' => 'get_order_status', 'arguments' => '{}']]],
    'empty call id' => [[manualFunctionCall('', 'get_order_status', '{}')]],
    'duplicate call id' => [[manualFunctionCall('same', 'get_order_status', '{}'), manualFunctionCall('same', 'get_order_status', '{}')]],
]);

test('enforces independent round and tool call limits', function (int $rounds, int $limit, array $calls) {
    config(['services.openai.agent_max_rounds' => $rounds, 'services.openai.agent_max_tool_calls' => $limit]);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(manualCallsResponse($calls))]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Check', null))
        ->toThrow(AgentException::class);
    Http::assertSentCount(1);
})->with([
    'round budget exhausted' => [1, 6, [manualFunctionCall('one', 'get_order_status', '{}')]],
    'tool budget exhausted' => [4, 0, [manualFunctionCall('one', 'get_order_status', '{}')]],
    'batch exceeds budget' => [4, 1, [manualFunctionCall('one', 'get_order_status', '{}'), manualFunctionCall('two', 'get_order_status', '{}')]],
]);

test('does not allow options to override typed request fields', function () {
    expect(fn () => new OpenAIResponseRequest('gpt-6-luna', 'Support', 'Hi', options: ['input' => 'untrusted']))
        ->toThrow(InvalidArgumentException::class);
});

test('enforces the total call budget across multiple rounds', function () {
    config(['services.openai.agent_max_tool_calls' => 1]);
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(manualCallsResponse([manualFunctionCall('first', 'unknown', '{}')], 'resp_first'))
        ->push(manualCallsResponse([manualFunctionCall('second', 'unknown', '{}')], 'resp_second'))]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Hi', null))
        ->toThrow(AgentException::class);
    Http::assertSentCount(2);
});

test('stops repeated tool requests at the maximum round count', function () {
    config(['services.openai.agent_max_rounds' => 2]);
    Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
        ->push(manualCallsResponse([manualFunctionCall('first', 'unknown', '{}')], 'resp_first'))
        ->push(manualCallsResponse([manualFunctionCall('second', 'unknown', '{}')], 'resp_second'))]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Hi', null))
        ->toThrow(AgentException::class);
    Http::assertSentCount(2);
});

test('rejects partial messages even if the response envelope claims completion', function () {
    $response = manualTextResponse();
    $response['output'][0]['status'] = 'incomplete';
    Http::fake(['https://api.openai.com/v1/responses' => Http::response($response)]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Hi', null))
        ->toThrow(AgentException::class);
});

test('fails safely before sending requests when credentials are missing', function () {
    config(['services.openai.api_key' => null]);

    expect(fn () => app(DirectOrderSupportAgent::class)->respond(User::factory()->make(), 'Hi', null))
        ->toThrow(AgentException::class);
    Http::assertNothingSent();
});
