<?php

namespace App\Services\AI\Data;

final readonly class AgentResult
{
    /** @param array<string, mixed> $usage */
    public function __construct(
        public string $text,
        public string $finalResponseId,
        public array $usage,
        public int $rounds,
        public int $toolCallCount,
    ) {}
}
