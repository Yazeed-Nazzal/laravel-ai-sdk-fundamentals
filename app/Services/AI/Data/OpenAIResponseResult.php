<?php

namespace App\Services\AI\Data;

use App\Services\AI\AgentException;

final readonly class OpenAIResponseResult
{
    /**
     * @param  list<array<string, mixed>>  $output
     * @param  array<string, mixed>  $usage
     * @param  array<string, mixed>|null  $incompleteDetails
     * @param  array<string, mixed>|null  $error
     */
    public function __construct(
        public string $id,
        public string $status,
        public array $output,
        public array $usage = [],
        public ?array $incompleteDetails = null,
        public ?array $error = null,
    ) {}

    public static function fromArray(mixed $data): self
    {
        if (! is_array($data) || ! is_string($data['id'] ?? null) || preg_match('/^resp_[A-Za-z0-9_-]+$/D', $data['id']) !== 1
            || ! is_string($data['status'] ?? null) || ! is_array($data['output'] ?? null) || ! array_is_list($data['output'])) {
            throw new AgentException('Malformed provider response.');
        }
        foreach ($data['output'] as $item) {
            if (! is_array($item) || ! is_string($item['type'] ?? null)) {
                throw new AgentException('Malformed provider output.');
            }
        }
        foreach (['usage', 'incomplete_details', 'error'] as $field) {
            if (isset($data[$field]) && ! is_array($data[$field])) {
                throw new AgentException('Malformed provider metadata.');
            }
        }

        return new self($data['id'], $data['status'], $data['output'], $data['usage'] ?? [], $data['incomplete_details'] ?? null, $data['error'] ?? null);
    }

    public function text(): string
    {
        $texts = [];
        foreach ($this->output as $item) {
            if ($item['type'] !== 'message') {
                continue;
            }
            if (isset($item['status']) && $item['status'] !== 'completed') {
                throw new AgentException('Provider message is not completed.');
            }
            if (! is_array($item['content'] ?? null) || ! array_is_list($item['content'])) {
                throw new AgentException('Malformed provider message.');
            }
            foreach ($item['content'] as $content) {
                if (! is_array($content)) {
                    throw new AgentException('Malformed provider content.');
                }
                if (($content['type'] ?? null) === 'output_text') {
                    if (! is_string($content['text'] ?? null)) {
                        throw new AgentException('Malformed provider text.');
                    }
                    $texts[] = $content['text'];
                }
            }
        }

        return implode("\n", $texts);
    }
}
