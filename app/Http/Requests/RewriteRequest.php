<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RewriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string|max:6000', 'revision' => 'required|integer|min:0',
            'mode' => ['required', Rule::in(array_keys(config('writing.modes')))],
            'action' => 'required|in:grammar,improve,natural,professional,friendly,romantic,shorten,expand',
        ];
    }
}
