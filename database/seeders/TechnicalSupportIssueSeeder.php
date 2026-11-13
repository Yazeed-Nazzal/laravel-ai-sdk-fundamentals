<?php

namespace Database\Seeders;

use App\Models\TechnicalSupportIssue;
use Illuminate\Database\Seeder;

class TechnicalSupportIssueSeeder extends Seeder
{
    public function run(): void
    {
        TechnicalSupportIssue::factory()->count(5)->create();
    }
}
