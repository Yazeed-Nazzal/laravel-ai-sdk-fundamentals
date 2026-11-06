<?php

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;

class ResumeSupportConversation
{
    public function __construct(private readonly ?string $responseId) {}

    public function handle(PendingStep $step, Closure $next): StepResponse|StepResult
    {
        $options = ['store' => true];
        if ($step->isFirstStep() && $this->responseId !== null) {
            $options['previous_response_id'] = $this->responseId;
        }

        return $next($step->withProviderOptions($options));
    }
}
