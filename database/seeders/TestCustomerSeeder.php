<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestCustomerSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        DB::transaction(function (): void {
            $customer = User::updateOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test Customer', 'password' => 'TestCustomer123!'],
            );

            foreach (['pending', 'shipped', 'delivered'] as $status) {
                Order::firstOrCreate(['user_id' => $customer->id, 'status' => $status]);
            }
        });
    }
}
