<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'revision' => 'required|integer|min:0', 'mode' => ['required', Rule::in(array_keys(config('writing.modes')))],
            'blocks' => 'present|array|max:200', 'blocks.*.id' => 'required|string|max:50|distinct',
            'blocks.*.text' => 'present|string|max:12000',
        ];
    }

    public function after(): array
    {
        return [function ($v) {
            $blocks = $this->input('blocks', []);
            if (is_array($blocks) && array_sum(array_map(fn ($b) => is_array($b) && is_string($b['text'] ?? null) ? mb_strlen($b['text']) : 0, $blocks)) > 12000) {
                $v->errors()->add('blocks', 'Analysis is limited to 12,000 characters.');
            }
        }];
    }
}
