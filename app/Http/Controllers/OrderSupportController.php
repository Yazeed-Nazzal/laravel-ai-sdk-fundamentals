<?php

namespace App\Http\Controllers;

use App\Ai\Agents\OrderSupportAgent;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\Data\FinishReason;

class OrderSupportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        try {
            $response = OrderSupportAgent::make(customer: $request->user())->prompt($validated['message']);
        } catch (RateLimitedException) {
            return response()->json(['message' => 'The assistant is busy. Please try again later.'], 429);
        } catch (AiException|RequestException) {
            return response()->json(['message' => 'The assistant is unavailable. Please try again.'], 503);
        }

        if (trim($response->text) === '' || $response->steps->last()?->finishReason !== FinishReason::Stop) {
            return response()->json(['message' => 'The assistant could not complete its answer. Please try again.'], 502);
        }

        return response()->json(['message' => $response->text]);
    }
}
