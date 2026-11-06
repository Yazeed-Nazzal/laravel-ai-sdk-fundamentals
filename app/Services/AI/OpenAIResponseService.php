<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIResponseService
{
    /** @return array{response_id: string, answer: string, usage: array<string, mixed>} */
    public function ask(string $question): array
    {
        $response = Http::withToken(config('services.openai.api_key'))
            ->acceptJson()->connectTimeout(5)->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model'),
                'instructions' => 'You are an Arabic customer support assistant.',
                'input' => $question,
                'max_output_tokens' => 300,
            ]);

        $response->throw();
        $data = $response->json();
        if (($data['status'] ?? null) !== 'completed') {
            throw new RuntimeException('AI response did not complete.');
        }
        $texts = [];
        foreach ($data['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $texts[] = $content['text'];
                }
            }
        }
        if ($texts === [] || ! is_string($data['id'] ?? null)) {
            throw new RuntimeException('AI response contains no text or response ID.');
        }

        return [
            'response_id' => $data['id'],
            'answer' => implode("\n", $texts),
            'usage' => $data['usage'] ?? [],
        ];
    }
}
