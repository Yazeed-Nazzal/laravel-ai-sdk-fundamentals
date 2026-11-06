<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\Contracts\AgentTool;
use App\Services\OrderService;
use Illuminate\Support\Facades\Validator;

class GetOrderStatus implements AgentTool
{
    public function __construct(private readonly OrderService $orders) {}

    public function name(): string
    {
        return 'get_order_status';
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'type' => 'function', 'name' => $this->name(),
            'description' => 'Read one order status for the authenticated customer.',
            'strict' => true,
            'parameters' => [
                'type' => 'object', 'properties' => ['order_id' => ['type' => 'integer', 'minimum' => 1, 'maximum' => PHP_INT_MAX]],
                'required' => ['order_id'], 'additionalProperties' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $authenticatedUser): array
    {
        $validator = Validator::make($arguments, ['order_id' => ['required', 'integer:strict', 'min:1', 'max:'.PHP_INT_MAX]]);
        if (array_diff(array_keys($arguments), ['order_id']) !== [] || $validator->fails()) {
            return ['error' => 'invalid_arguments'];
        }

        return $this->orders->getOrderStatus((int) $authenticatedUser->getAuthIdentifier(), $validator->validated()['order_id']);
    }
}
