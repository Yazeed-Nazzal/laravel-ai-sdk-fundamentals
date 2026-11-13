<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SupportStaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        // Keep this demo login repeatable without creating duplicate employees.
        $staff = User::firstOrNew(['email' => 'support@example.com']);
        $staff->forceFill([
            'name' => 'Support Staff',
            'password' => 'SupportStaff123!',
            'is_support_staff' => true,
        ])->save();
    }
}
