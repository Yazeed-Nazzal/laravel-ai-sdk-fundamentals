<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportConversationDetailResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['conversation_id' => $this->id, 'messages' => SupportMessageResource::collection($this->whenLoaded('messages'))];
    }
}
