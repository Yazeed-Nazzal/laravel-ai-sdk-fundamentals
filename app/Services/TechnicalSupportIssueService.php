<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AI\Data\SupportIssueClassification;
use App\Services\AI\IssueClassificationAgent;
use Illuminate\Support\Facades\DB;

class TechnicalSupportIssueService
{
    public function __construct(
        private IssueClassificationAgent $agent,
        private SupportIntakeConversationService $conversations,
        private TechnicalSupportTicketService $tickets,
        private SupportIntakeReplyService $replies,
    ) {}

    public function activeFor(User $user): ?SupportConversation
    {
        return $this->conversations->activeFor($user);
    }

    /** @return array{ticket_id: ?int, message: string} */
    public function respond(User $user, string $message): array
    {
        return $this->conversations->exclusivelyFor($user, function () use ($user, $message): array {
            $conversation = $this->conversations->startOrResume($user);
            $description = $this->conversations->descriptionFor($conversation, $message);

            // Provider calls remain outside the database transaction; failures save no turn.
            $result = $this->agent->classify($message, $conversation->last_response_id);
            $classification = SupportIssueClassification::fromArray($result['classification']);

            return $this->commitTurn($conversation, $message, $description, $result['response_id'], $classification);
        });
    }

    public function resetFor(User $user): void
    {
        $this->conversations->resetFor($user);
    }

    /** @return array{ticket_id: ?int, message: string} */
    private function commitTurn(SupportConversation $conversation, string $message, string $description, string $responseId, SupportIssueClassification $classification): array
    {
        // The ticket, messages and response pointer must succeed or roll back together.
        return DB::transaction(function () use ($conversation, $message, $description, $responseId, $classification): array {
            $current = $this->conversations->lockCurrent($conversation);
            $issue = $classification->isComplete()
                ? $this->tickets->create($current, $description, $classification)
                : null;
            $reply = $issue === null
                ? $this->replies->question($classification, $description)
                : $this->replies->receipt($issue->id, $description);

            $this->conversations->saveTurn($current, $message, $reply, $responseId, $classification->isComplete());

            return ['ticket_id' => $issue?->id, 'message' => $reply];
        });
    }
}
