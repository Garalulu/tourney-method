<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'main_mode' => ['sometimes', 'nullable', 'in:osu,taiko,catch,mania'],
            'discord_webhook_url' => ['sometimes', 'nullable', 'url', 'regex:#^https://discord\.com/api/webhooks/\d+/[\w-]+$#'],
            'notify_registration' => ['sometimes', 'boolean'],
            'notify_stream' => ['sometimes', 'boolean'],
            'in_app_notification_preferences' => ['sometimes', 'array'],
            'in_app_notification_preferences.*' => ['boolean'],
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
            'main_mode.in' => 'The selected game mode is invalid. Valid modes are: osu, taiko, catch, mania.',
            'discord_webhook_url.url' => 'The webhook URL must be a valid URL.',
            'discord_webhook_url.regex' => 'The webhook URL must be a valid Discord webhook URL (https://discord.com/api/webhooks/...).',
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Per OpenAPI spec: Return 400 BadRequest for validation errors.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors()->toArray(),
                ], 400)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Handle a failed authorization attempt.
     * Per OpenAPI spec: Return 401 Unauthorized.
     */
    protected function failedAuthorization(): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Unauthenticated.',
                ], 401)
            );
        }

        parent::failedAuthorization();
    }
}
