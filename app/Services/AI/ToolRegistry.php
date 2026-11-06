<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Contracts\AgentTool;
use InvalidArgumentException;
use JsonException;

class ToolRegistry
{
    /** @var array<string, AgentTool> */
    private array $tools = [];

    /** @param list<AgentTool> $tools */
    public function __construct(array $tools)
    {
        foreach ($tools as $tool) {
            if (isset($this->tools[$tool->name()])) {
                throw new InvalidArgumentException('Duplicate tool name.');
            }
            $this->tools[$tool->name()] = $tool;
        }
    }

    /** @return list<array<string, mixed>> */
    public function schemas(): array
    {
        return array_values(array_map(fn (AgentTool $tool): array => $tool->schema(), $this->tools));
    }

    /** @return array<string, mixed> */
    public function dispatch(string $name, string $arguments, User $customer): array
    {
        if (! isset($this->tools[$name])) {
            return ['error' => 'unknown_tool'];
        }
        try {
            $decoded = json_decode($arguments, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['error' => 'invalid_arguments'];
        }
        if (! $decoded instanceof \stdClass) {
            return ['error' => 'invalid_arguments'];
        }

        return $this->tools[$name]->execute(get_object_vars($decoded), $customer);
    }
}
