<?php

namespace App\Services;

use App\Models\Order;

class OrderService
{
    /** @return array{order_id: int, status: string}|array{error: string} */
    public function getOrderStatus(int $customerId, int $orderId): array
    {
        $order = Order::query()->where('user_id', $customerId)->whereKey($orderId)->first(['id', 'status']);
        if ($order === null) {
            return ['error' => 'order_not_found'];
        }

        return ['order_id' => $order->id, 'status' => $order->status];
    }
}
