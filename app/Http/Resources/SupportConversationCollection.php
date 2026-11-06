<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupportConversationCollection extends ResourceCollection
{
    public static $wrap = null;

    public $collects = SupportConversationResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['conversations' => $this->collection];
    }
}
