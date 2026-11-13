<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'message', 'category', 'reported_order_id', 'priority', 'requires_human', 'status', 'priority_escalated_at', 'support_conversation_id'])]
class TechnicalSupportIssue extends Model
{
    use HasFactory;

    public const CATEGORIES = ['payment_issue', 'delivery_issue', 'order_issue', 'technical_issue', 'account_issue', 'other'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    protected function casts(): array
    {
        return ['requires_human' => 'boolean', 'reported_order_id' => 'integer', 'priority_escalated_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }
}
