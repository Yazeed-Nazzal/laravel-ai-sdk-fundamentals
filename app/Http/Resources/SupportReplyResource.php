<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportReplyResource extends JsonResource
{
    public static $wrap = null;

    /** @return array{conversation_id: int, message: string} */
    public function toArray(Request $request): array
    {
        return ['conversation_id' => $this->resource['conversation_id'], 'message' => $this->resource['message']];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode($this->resource['status']);
    }
}
