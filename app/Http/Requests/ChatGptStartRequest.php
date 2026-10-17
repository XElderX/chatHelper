<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatGptStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['account_id' => 'nullable|integer|exists:chatgpt_accounts,id'];
    }
}
