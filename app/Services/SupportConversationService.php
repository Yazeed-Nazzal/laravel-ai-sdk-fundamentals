<?php

namespace App\Services;

use App\Ai\Agents\OrderSupportAgent;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\Data\FinishReason;

class SupportConversationService
{
    /** @return Collection<int, SupportConversation> */
    public function listFor(User $customer): Collection
    {
        return SupportConversation::query()->where('user_id', $customer->id)->where('agent_driver', 'sdk')
            ->latest('updated_at')->latest('id')->get(['id', 'created_at', 'updated_at']);
    }

    public function findFor(User $customer, int $conversationId): SupportConversation
    {
        return SupportConversation::query()->where('user_id', $customer->id)->where('agent_driver', 'sdk')->findOrFail($conversationId);
    }

    public function historyFor(User $customer, int $conversationId): SupportConversation
    {
        return $this->findFor($customer, $conversationId)->load(['messages' => fn ($query) => $query->orderBy('id')]);
    }

    /**
     * @param  array{message: string, conversation_id?: int|null}  $validated
     * @return array{conversation_id: int, message: string, status: int}
     */
    public function send(User $customer, array $validated): array
    {
        $conversation = isset($validated['conversation_id'])
            ? $this->findFor($customer, (int) $validated['conversation_id'])
            : SupportConversation::create(['user_id' => $customer->id, 'agent_driver' => 'sdk']);
        $lock = Cache::lock('support-conversation:'.$conversation->id, 180);
        if (! $lock->get()) {
            return ['conversation_id' => $conversation->id, 'message' => 'A reply is already being generated. Please wait.', 'status' => 409];
        }
        try {
            $conversation->refresh();
            $previousId = $conversation->last_response_id;
            try {
                $response = OrderSupportAgent::make(customer: $customer, previousResponseId: $previousId)
                    ->prompt($validated['message'], provider: 'openai', timeout: 30);
            } catch (RateLimitedException) {
                return ['conversation_id' => $conversation->id, 'message' => 'The assistant is busy. Please try again later.', 'status' => 429];
            } catch (AiException|RequestException) {
                return ['conversation_id' => $conversation->id, 'message' => 'The assistant is unavailable. Please try again.', 'status' => 503];
            }
            $responseId = $response->raw?->json('id');
            if (trim($response->text) === '' || $response->steps->last()?->finishReason !== FinishReason::Stop || ! is_string($responseId) || trim($responseId) === '') {
                return ['conversation_id' => $conversation->id, 'message' => 'The assistant could not complete its answer. Please try again.', 'status' => 502];
            }
            $saved = DB::transaction(function () use ($conversation, $validated, $response, $responseId, $previousId): bool {
                $updated = SupportConversation::query()->whereKey($conversation->id)->where('last_response_id', $previousId)
                    ->update(['last_response_id' => $responseId, 'updated_at' => now()]);
                if ($updated !== 1) {
                    return false;
                }
                $conversation->messages()->createMany([
                    ['role' => 'user', 'content' => $validated['message']],
                    ['role' => 'assistant', 'content' => $response->text],
                ]);

                return true;
            });
            if (! $saved) {
                return ['conversation_id' => $conversation->id, 'message' => 'Conversation changed. Reload it before sending again.', 'status' => 409];
            }

            return ['conversation_id' => $conversation->id, 'message' => $response->text, 'status' => 200];
        } finally {
            $lock->release();
        }
    }
}
