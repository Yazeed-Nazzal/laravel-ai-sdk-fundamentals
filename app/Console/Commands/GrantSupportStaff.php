<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantSupportStaff extends Command
{
    protected $signature = 'support-staff:grant {email : Email of an existing support employee}';

    protected $description = 'Allow an existing user to manage the technical support queue';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No existing user has that email address.');

            return self::FAILURE;
        }

        // This privileged flag is deliberately excluded from User's fillable fields.
        $user->forceFill(['is_support_staff' => true])->save();
        $this->info('Support staff access granted.');

        return self::SUCCESS;
    }
}
