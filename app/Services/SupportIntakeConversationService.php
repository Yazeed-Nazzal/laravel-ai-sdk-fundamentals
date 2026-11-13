<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AI\AgentException;
use Closure;
use Illuminate\Support\Facades\Cache;

class SupportIntakeConversationService
{
    public function activeFor(User $user): ?SupportConversation
    {
        return SupportConversation::where('user_id', $user->id)->where('agent_driver', 'intake')
            ->where('intake_status', 'active')->latest('id')->first();
    }

    public function startOrResume(User $user): SupportConversation
    {
        return $this->activeFor($user) ?? SupportConversation::create([
            'user_id' => $user->id, 'agent_driver' => 'intake', 'intake_status' => 'active',
        ]);
    }

    public function descriptionFor(SupportConversation $conversation, string $message): string
    {
        $history = $conversation->messages()->orderBy('id')->get();
        if ($history->count() >= 40) {
            throw new AgentException('This conversation is too long. Please start a new report.', 422);
        }
        $description = $history->where('role', 'user')->pluck('content')->push($message)->implode("\n\n");
        if (mb_strlen($description, '8bit') > 60000) {
            throw new AgentException('This report is too long. Please start a shorter report.', 422);
        }

        return $description;
    }

    /** Must run inside the transaction that commits this conversation turn. */
    public function lockCurrent(SupportConversation $conversation): SupportConversation
    {
        $current = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
        if ($current->intake_status !== 'active' || $current->last_response_id !== $conversation->last_response_id) {
            throw new AgentException('Conversation changed. Reload before sending again.', 409);
        }

        return $current;
    }

    public function saveTurn(SupportConversation $conversation, string $message, string $reply, string $responseId, bool $isComplete): void
    {
        $conversation->messages()->createMany([
            ['role' => 'user', 'content' => $message],
            ['role' => 'assistant', 'content' => $reply],
        ]);
        $conversation->update(['last_response_id' => $responseId, 'intake_status' => $isComplete ? 'completed' : 'active']);
    }

    public function resetFor(User $user): void
    {
        $this->exclusivelyFor($user, function () use ($user): void {
            // Retain abandoned history for review, without affecting completed tickets.
            $this->activeFor($user)?->update(['intake_status' => 'abandoned']);
        });
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function exclusivelyFor(User $user, Closure $operation): mixed
    {
        // User-level locking prevents two devices from forking the same response chain.
        $lock = Cache::lock('support-intake:'.$user->id, 90);
        if (! $lock->get()) {
            throw new AgentException('A reply is already being generated. Please wait.', 409);
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }
}
