<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendManualSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'previous_response_id' => ['prohibited'],
            'last_response_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }
}
