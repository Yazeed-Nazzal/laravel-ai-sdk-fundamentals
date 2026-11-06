<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendManualSupportMessageRequest;
use App\Http\Resources\ManualConversationCollection;
use App\Http\Resources\ManualConversationDetailResource;
use App\Http\Resources\ManualReplyResource;
use App\Services\ManualConversationService;
use Illuminate\Http\Request;

class ManualConversationController extends Controller
{
    public function __construct(private readonly ManualConversationService $conversations) {}

    public function index(Request $request): ManualConversationCollection
    {
        return new ManualConversationCollection($this->conversations->listFor($request->user()));
    }

    public function show(Request $request, int $conversation): ManualConversationDetailResource
    {
        return new ManualConversationDetailResource($this->conversations->historyFor($request->user(), $conversation));
    }

    public function store(SendManualSupportMessageRequest $request): ManualReplyResource
    {
        return new ManualReplyResource($this->conversations->send($request->user(), $request->validated()));
    }
}
