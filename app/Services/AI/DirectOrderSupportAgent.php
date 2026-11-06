<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Data\AgentResult;
use App\Services\AI\Data\OpenAIResponseRequest;
use App\Services\AI\Tools\GetOrderStatus;

class DirectOrderSupportAgent
{
    private readonly ToolRegistry $tools;

    public function __construct(private readonly OpenAIResponseService $client, GetOrderStatus $orderStatus)
    {
        $this->tools = new ToolRegistry([$orderStatus]);
    }

    public function respond(User $customer, string $message, ?string $previousResponseId): AgentResult
    {
        $maxRounds = (int) config('services.openai.agent_max_rounds', 4);
        $maxCalls = (int) config('services.openai.agent_max_tool_calls', 6);
        $deadline = microtime(true) + 120;
        $input = $message;
        $calls = 0;
        $usage = [];
        $seenCallIds = [];
        for ($round = 1; $round <= $maxRounds; $round++) {
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 1) {
                throw new AgentException('Agent execution deadline exceeded.');
            }
            $response = $this->client->createResponse(new OpenAIResponseRequest(
                model: config('services.openai.model'),
                instructions: OrderSupportInstructions::text(),
                input: $input,
                tools: $this->tools->schemas(),
                previousResponseId: $previousResponseId,
                maxOutputTokens: 1000,
                options: ['store' => true],
                timeout: min(30, $remaining),
            ));
            $usage = $this->aggregateUsage($usage, $response->usage);
            if ($response->status !== 'completed') {
                throw new AgentException('Provider response is not completed.');
            }
            $toolCalls = [];
            foreach ($response->output as $item) {
                if ($item['type'] === 'function_call') {
                    $toolCalls[] = $item;
                } elseif (! in_array($item['type'], ['message', 'reasoning'], true)) {
                    throw new AgentException('Unsupported provider output type.');
                }
            }
            if ($toolCalls === []) {
                $text = $response->text();
                if (trim($text) === '') {
                    throw new AgentException('Provider response contains no answer.');
                }

                return new AgentResult($text, $response->id, $usage, $round, $calls);
            }
            if ($round === $maxRounds || $calls + count($toolCalls) > $maxCalls) {
                throw new AgentException('Agent execution limit exceeded.');
            }
            foreach ($toolCalls as $call) {
                if (! is_string($call['call_id'] ?? null) || trim($call['call_id']) === ''
                    || isset($seenCallIds[$call['call_id']]) || ! is_string($call['name'] ?? null)
                    || ! is_string($call['arguments'] ?? null)
                    || (isset($call['status']) && $call['status'] !== 'completed')) {
                    throw new AgentException('Malformed function call.');
                }
                $seenCallIds[$call['call_id']] = true;
            }
            $input = [];
            foreach ($toolCalls as $call) {
                if (microtime(true) >= $deadline) {
                    throw new AgentException('Agent execution deadline exceeded.');
                }
                $result = $this->tools->dispatch($call['name'], $call['arguments'], $customer);
                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $call['call_id'],
                    'output' => json_encode($result, JSON_THROW_ON_ERROR),
                ];
                $calls++;
            }
            $previousResponseId = $response->id;
        }
        throw new AgentException('Agent execution limit exceeded.');
    }

    /**
     * @param  array<string, mixed>  $total
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function aggregateUsage(array $total, array $current): array
    {
        foreach ($current as $key => $value) {
            if (is_array($value)) {
                $total[$key] = $this->aggregateUsage(is_array($total[$key] ?? null) ? $total[$key] : [], $value);
            } elseif (is_int($value) && $value >= 0 && str_contains($key, 'tokens')) {
                $total[$key] = (is_int($total[$key] ?? null) ? $total[$key] : 0) + $value;
            }
        }

        return $total;
    }
}
