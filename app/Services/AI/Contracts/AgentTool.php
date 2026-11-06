<?php

namespace App\Services\AI\Contracts;

use App\Models\User;

interface AgentTool
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function schema(): array;

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $authenticatedUser): array;
}
