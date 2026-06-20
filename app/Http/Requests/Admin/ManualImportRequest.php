<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ManualImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'topic_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999'],
            'topic_ids' => ['sometimes', 'nullable', 'array', 'max:50'],
            'topic_ids.*' => ['integer', 'min:1', 'max:999999999'],
        ];
    }
}
