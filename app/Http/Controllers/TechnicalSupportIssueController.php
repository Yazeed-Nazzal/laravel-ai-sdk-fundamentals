<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTechnicalSupportIssueRequest;
use App\Models\TechnicalSupportIssue;
use App\Services\AI\AgentException;
use App\Services\TechnicalSupportIssueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TechnicalSupportIssueController extends Controller
{
    public function create(Request $request, TechnicalSupportIssueService $service): View
    {
        $conversation = $service->activeFor($request->user());

        return view('technical-support-chat', [
            'issues' => TechnicalSupportIssue::where('user_id', $request->user()->id)->latest('id')->paginate(20),
            'draft' => $conversation?->messages()->orderBy('id')->get(['role', 'content'])->toArray() ?? [],
        ]);
    }

    public function store(StoreTechnicalSupportIssueRequest $request, TechnicalSupportIssueService $service): JsonResponse
    {
        try {
            $result = $service->respond($request->user(), $request->validated('message'));
        } catch (AgentException $exception) {
            if (in_array($exception->status, [409, 422], true)) {
                return response()->json(['message' => $exception->getMessage()], $exception->status);
            }
            report($exception);

            return response()->json(['message' => 'Classification is unavailable. No issue was saved. Please try again.'], 503);
        }

    }

    public function destroy(Request $request, TechnicalSupportIssueService $service): JsonResponse
    {
        try {
            $service->resetFor($request->user());
        } catch (AgentException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['message' => 'New report started. Please describe your issue.']);
    }

    public function index(Request $request): View
    {
        $validated = $request->validate(['status' => ['nullable', 'in:open,solved']]);
        $issues = TechnicalSupportIssue::with(['user:id,name,email', 'conversation.messages'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->oldest('id')->paginate(20)->withQueryString();

        return view('technical-support-issues', compact('issues'));
    }

    public function update(Request $request, TechnicalSupportIssue $issue): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:open,solved']]);
        // Reopening starts a fresh waiting period; solved issues never escalate.
        if ($issue->status !== $validated['status']) {
            $issue->update(['status' => $validated['status'], 'priority_escalated_at' => now()]);
        }

        return back()->with('status', "Issue #{$issue->id} updated.");
    }
}
