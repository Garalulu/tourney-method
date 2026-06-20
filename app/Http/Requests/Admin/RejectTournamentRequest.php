<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RejectTournamentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'The rejection reason must not exceed 1000 characters.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Per OpenAPI spec: Return 400 BadRequest for validation errors.
     */
    protected function failedValidation(Validator $validator): void
    {
        // Always return JSON for API endpoints per OpenAPI spec
        throw new HttpResponseException(
            response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()->toArray(),
            ], 400)
        );
    }
}
