<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectionSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['provider' => 'required|in:openai,chatgpt,mock', 'account_id' => 'nullable|integer|exists:chatgpt_accounts,id'];
    }
}
