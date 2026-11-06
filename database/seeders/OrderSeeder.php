<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }
        foreach (['alice', 'bob'] as $name) {
            $customer = User::firstOrCreate(
                ['email' => $name.'@example.test'],
                ['name' => ucfirst($name), 'password' => bin2hex(random_bytes(32))],
            );
            foreach (['pending', 'shipped', 'delivered'] as $status) {
                Order::firstOrCreate(['user_id' => $customer->id, 'status' => $status]);
            }
        }
    }
}
