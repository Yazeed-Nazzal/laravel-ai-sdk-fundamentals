<?php

namespace App\Services\AI\Data;

use InvalidArgumentException;

final readonly class OpenAIResponseRequest
{
    /**
     * @param  string|list<array<string, mixed>>  $input
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options  Backend-only provider options.
     */
    public function __construct(
        public string $model,
        public string $instructions,
        public string|array $input,
        public array $tools = [],
        public ?string $previousResponseId = null,
        public int $maxOutputTokens = 300,
        public array $options = [],
        public int $timeout = 30,
    ) {
        if (trim($model) === '' || $maxOutputTokens < 1 || $timeout < 1) {
            throw new InvalidArgumentException('Invalid OpenAI request configuration.');
        }
        if (array_intersect(array_keys($options), ['model', 'instructions', 'input', 'tools', 'previous_response_id', 'max_output_tokens']) !== []) {
            throw new InvalidArgumentException('Provider options cannot override typed request fields.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_merge($this->options, [
            'model' => $this->model,
            'instructions' => $this->instructions,
            'input' => $this->input,
            'max_output_tokens' => $this->maxOutputTokens,
        ]);
        if ($this->tools !== []) {
            $payload['tools'] = $this->tools;
        }
        if ($this->previousResponseId !== null) {
            $payload['previous_response_id'] = $this->previousResponseId;
        }

        return $payload;
    }
}
