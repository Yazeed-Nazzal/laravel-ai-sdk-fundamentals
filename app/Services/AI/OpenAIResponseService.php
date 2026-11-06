<?php

namespace App\Services\AI;

use App\Services\AI\Data\OpenAIResponseRequest;
use App\Services\AI\Data\OpenAIResponseResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAIResponseService
{
    public function createResponse(OpenAIResponseRequest $request): OpenAIResponseResult
    {
        $apiKey = config('services.openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AgentException('OpenAI credentials are not configured.', 503);
        }
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->connectTimeout(5)->timeout($request->timeout)
                ->post('https://api.openai.com/v1/responses', $request->toArray());
            $response->throw();
        } catch (ConnectionException $exception) {
            throw new AgentException('Provider connection failed.', 503, $exception);
        } catch (RequestException $exception) {
            throw new AgentException('Provider request failed.', $exception->response->status() === 429 ? 429 : 503, $exception);
        }

        return OpenAIResponseResult::fromArray($response->json());
    }

    /** @return array{response_id: string, answer: string, usage: array<string, mixed>} */
    public function ask(string $question): array
    {
        $response = $this->createResponse(new OpenAIResponseRequest(
            model: config('services.openai.model'),
            instructions: 'You are an Arabic customer support assistant.',
            input: $question,
        ));
        if ($response->status !== 'completed' || trim($response->text()) === '') {
            throw new AgentException('AI response did not complete or contains no text.');
        }

        return ['response_id' => $response->id, 'answer' => $response->text(), 'usage' => $response->usage];
    }
}
