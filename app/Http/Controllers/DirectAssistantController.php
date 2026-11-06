<?php

namespace App\Http\Controllers;

use App\Services\AI\OpenAIResponseService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DirectAssistantController extends Controller
{
    public function __invoke(Request $request, OpenAIResponseService $assistant): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        try {
            $result = $assistant->ask($validated['message']);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return response()->json(['message' => 'The assistant is unavailable. Please try again.'], 503);
        }

        return response()->json(['message' => $result['answer']]);
    }
}
