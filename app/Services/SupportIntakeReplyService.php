<?php

namespace App\Services;

use App\Services\AI\Data\SupportIssueClassification;

class SupportIntakeReplyService
{
    public function question(SupportIssueClassification $classification, string $description): string
    {
        $question = trim($classification->followUpQuestion);
        if ($question !== '') {
            return $question;
        }
        if ($classification->needsOrderId()) {
            return $this->isArabic($description)
                ? 'ما رقم الطلب؟ إذا لم يكن متوفراً، أخبرني بذلك.'
                : 'What is the order ID? If it is unavailable, please let me know.';
        }

        return $this->isArabic($description)
            ? 'هل يمكنك وصف المشكلة وما الذي حدث؟'
            : 'Could you describe the problem and what happened?';
    }

    public function receipt(int $ticketId, string $description): string
    {
        return $this->isArabic($description)
            ? "شكراً لك! تم تسجيل طلب الدعم الخاص بك برقم #{$ticketId}. سيتابع فريق الدعم مشكلتك."
            : "Thank you! Your support ticket is #{$ticketId}. Our support team will review your issue.";
    }

    private function isArabic(string $description): bool
    {
        return preg_match('/\p{Arabic}/u', $description) === 1;
    }
}
