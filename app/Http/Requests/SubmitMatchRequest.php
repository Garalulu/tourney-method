<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class SubmitMatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated to submit matches
        return Auth::check();
    }

    /**
     * Handle a failed validation attempt.
     * Per OpenAPI spec: Return 400 BadRequest for validation errors
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->toArray(),
                ], 400)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mp_link' => [
                'required',
                'url',
                'regex:/^https?:\/\/osu\.ppy\.sh\/(?:community\/matches|mp)\/\d+$/',
                // Note: Duplicate checking moved to controller for proper 409 response
            ],
            'tournament_id' => [
                'nullable',
                'exists:tournaments,id',
            ],
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
            'mp_link.required' => 'Please provide a multiplayer link.',
            'mp_link.url' => 'The multiplayer link must be a valid URL.',
            'mp_link.regex' => 'Invalid multiplayer link format. Use: https://osu.ppy.sh/community/matches/123456 or https://osu.ppy.sh/mp/123456',
            'tournament_id.exists' => 'The selected tournament does not exist.',
        ];
    }

    /**
     * Get the match ID extracted from the MP link.
     */
    public function getMatchId(): int
    {
        preg_match('~(?:matches|mp)/(\d+)~', $this->input('mp_link'), $matches);

        return (int) $matches[1];
    }
}
