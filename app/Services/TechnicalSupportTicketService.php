<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\TechnicalSupportIssue;
use App\Services\AI\Data\SupportIssueClassification;

class TechnicalSupportTicketService
{
    public function create(SupportConversation $conversation, string $description, SupportIssueClassification $classification): TechnicalSupportIssue
    {
        return TechnicalSupportIssue::create([
            'user_id' => $conversation->user_id,
            'support_conversation_id' => $conversation->id,
            'message' => $description,
            'category' => $classification->category,
            'reported_order_id' => $classification->orderId,
            'priority' => $classification->priority,
            'requires_human' => $classification->needsHumanReview(),
            'status' => 'open',
            'priority_escalated_at' => now(),
        ]);
    }
}
