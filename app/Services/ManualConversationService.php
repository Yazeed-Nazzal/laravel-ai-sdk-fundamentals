<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AI\AgentException;
use App\Services\AI\DirectOrderSupportAgent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ManualConversationService
{
    public function __construct(private readonly DirectOrderSupportAgent $agent) {}

    /** @return Collection<int, SupportConversation> */
    public function listFor(User $customer): Collection
    {
        return SupportConversation::query()->where('user_id', $customer->id)->where('agent_driver', 'manual')
            ->latest('updated_at')->latest('id')->get(['id', 'created_at', 'updated_at']);
    }

    public function findFor(User $customer, int $conversationId): SupportConversation
    {
        return SupportConversation::query()->where('user_id', $customer->id)->where('agent_driver', 'manual')->findOrFail($conversationId);
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
            : SupportConversation::create(['user_id' => $customer->id, 'agent_driver' => 'manual']);
        $lock = Cache::lock('support-conversation:'.$conversation->id, 180);
        if (! $lock->get()) {
            return ['conversation_id' => $conversation->id, 'message' => 'A reply is already being generated. Please wait.', 'status' => 409];
        }
        try {
            $conversation->refresh();
            $previousId = $conversation->last_response_id;
            try {
                $result = $this->agent->respond($customer, $validated['message'], $previousId);
            } catch (AgentException $exception) {
                $message = match ($exception->status) {
                    429 => 'The assistant is busy. Please try again later.',
                    503 => 'The assistant is unavailable. Please try again.',
                    default => 'The assistant could not complete its answer. Please try again.',
                };

                return ['conversation_id' => $conversation->id, 'message' => $message, 'status' => $exception->status];
            }
            DB::transaction(function () use ($conversation, $validated, $result, $previousId): void {
                $updated = SupportConversation::query()->whereKey($conversation->id)
                    ->where('last_response_id', $previousId)
                    ->update(['last_response_id' => $result->finalResponseId, 'updated_at' => now()]);
                if ($updated !== 1) {
                    throw new AgentException('Conversation changed while generating the reply.', 409);
                }
                $conversation->messages()->createMany([
                    ['role' => 'user', 'content' => $validated['message']],
                    ['role' => 'assistant', 'content' => $result->text],
                ]);
            });

            return ['conversation_id' => $conversation->id, 'message' => $result->text, 'status' => 200];
        } catch (AgentException) {
            return ['conversation_id' => $conversation->id, 'message' => 'Conversation changed. Reload it before sending again.', 'status' => 409];
        } finally {
            $lock->release();
        }
    }
}
