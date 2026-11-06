<?php

namespace App\Services\AI;

use RuntimeException;
use Throwable;

class AgentException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
