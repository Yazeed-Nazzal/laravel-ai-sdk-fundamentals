<?php

namespace App\Services\AI\Data;

final readonly class SupportIssueClassification
{
    public function __construct(
        public string $category,
        public ?int $orderId,
        public string $priority,
        public bool $requiresHuman,
        public bool $hasIssueDetails,
        public bool $orderIdUnavailable,
        public string $followUpQuestion,
    ) {}

    /** @param array{category: string, order_id: ?int, priority: string, requires_human: bool, has_issue_details: bool, order_id_unavailable: bool, follow_up_question: string} $classification */
    public static function fromArray(array $classification): self
    {
        return new self(
            $classification['category'], $classification['order_id'], $classification['priority'],
            $classification['requires_human'], $classification['has_issue_details'],
            $classification['order_id_unavailable'], $classification['follow_up_question'],
        );
    }

    public function needsOrderId(): bool
    {
        return in_array($this->category, ['payment_issue', 'delivery_issue', 'order_issue'], true)
            && $this->orderId === null && ! $this->orderIdUnavailable;
    }

    public function isComplete(): bool
    {
        return $this->hasIssueDetails && ! $this->needsOrderId();
    }

    public function needsHumanReview(): bool
    {
        return $this->requiresHuman || $this->orderIdUnavailable;
    }
}
