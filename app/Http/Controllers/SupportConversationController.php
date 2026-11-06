<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendSupportMessageRequest;
use App\Http\Resources\SupportConversationCollection;
use App\Http\Resources\SupportConversationDetailResource;
use App\Http\Resources\SupportReplyResource;
use App\Services\SupportConversationService;
use Illuminate\Http\Request;

class SupportConversationController extends Controller
{
    public function __construct(private readonly SupportConversationService $conversations) {}

    public function index(Request $request): SupportConversationCollection
    {
        return new SupportConversationCollection($this->conversations->listFor($request->user()));
    }

    public function show(Request $request, int $conversation): SupportConversationDetailResource
    {
        return new SupportConversationDetailResource($this->conversations->historyFor($request->user(), $conversation));
    }

    public function store(SendSupportMessageRequest $request): SupportReplyResource
    {
        return new SupportReplyResource($this->conversations->send($request->user(), $request->validated()));
    }
}
