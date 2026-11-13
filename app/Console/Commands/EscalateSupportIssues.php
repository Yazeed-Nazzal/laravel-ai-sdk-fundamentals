<?php

namespace App\Console\Commands;

use App\Models\TechnicalSupportIssue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EscalateSupportIssues extends Command
{
    protected $signature = 'support-issues:escalate';

    protected $description = 'Increase unresolved issue priority after each waiting day';

    public function handle(): int
    {
        $cutoff = now()->subDay();
        TechnicalSupportIssue::where('status', 'open')->whereIn('priority', ['low', 'medium'])
            ->where('priority_escalated_at', '<=', $cutoff)->chunkById(200, function ($issues) use ($cutoff): void {
                foreach ($issues as $issue) {
                    DB::transaction(function () use ($issue, $cutoff): void {
                        $current = TechnicalSupportIssue::whereKey($issue->id)->lockForUpdate()->first();
                        if ($current === null || $current->status !== 'open' || $current->priority === 'high' || $current->priority_escalated_at->gt($cutoff)) {
                            return;
                        }
                        // Catch up missed days, cap at high, and make reruns safe.
                        $days = (int) $current->priority_escalated_at->diffInDays(now());
                        $priority = $current->priority === 'medium' || $days >= 2 ? 'high' : 'medium';
                        $current->update(['priority' => $priority, 'priority_escalated_at' => $current->priority_escalated_at->addDays($days)]);
                    });
                }
            });
        $this->info('Unresolved issue priorities updated.');

        return self::SUCCESS;
    }
}
