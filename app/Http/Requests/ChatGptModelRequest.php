<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatGptModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['account_id' => 'required|integer|exists:chatgpt_accounts,id', 'model' => 'required|string|max:200'];
    }
}
