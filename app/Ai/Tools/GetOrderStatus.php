<?php

namespace App\Ai\Tools;

use App\Services\OrderService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetOrderStatus implements Tool
{
    public function __construct(private readonly OrderService $orders, private readonly int $customerId) {}

    public function description(): string
    {
        return 'Read the status of one order belonging to the authenticated customer.';
    }

    public function handle(Request $request): string
    {
        if (array_diff(array_keys($request->all()), ['order_id']) !== []) {
            return '{"error":"invalid_arguments"}';
        }
        try {
            $validated = $request->validate([
                'order_id' => ['required', 'integer:strict', 'min:1', 'max:'.PHP_INT_MAX],
            ]);
        } catch (ValidationException) {
            return '{"error":"invalid_arguments"}';
        }

        return json_encode($this->orders->getOrderStatus($this->customerId, $validated['order_id']), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['order_id' => $schema->integer()->min(1)->max(PHP_INT_MAX)->required()];
    }
}
