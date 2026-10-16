<?php

namespace App\Http\Requests;

use App\Rules\RichText;
use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => 'required|string|max:160', 'content' => ['required', 'array', new RichText], 'version' => ! $this->isMethod('post') ? 'required|integer|min:1' : 'prohibited'];
    }
}
