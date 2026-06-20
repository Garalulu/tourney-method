<?php

namespace App\Http\Requests\Admin;

use App\Services\CldrService;
use App\Services\TournamentMetadataNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TournamentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'master']);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(app(TournamentMetadataNormalizer::class)->normalize($this->all()));
    }

    /**
     * Get the proper failed validation response for the request.
     * Override to return JSON per OpenAPI spec.
     *
     * @return JsonResponse
     */
    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 400));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // PATCH allows partial updates - all fields optional per OpenAPI spec
        return [
            'title' => 'sometimes|required|string|max:256',
            'modes' => 'sometimes|required|array',
            'modes.*' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    // Allow both string modes and enhanced format arrays
                    if (is_string($value)) {
                        if (! in_array($value, ['osu', 'taiko', 'catch', 'mania', 'fruits'])) {
                            $fail("The selected {$attribute} is invalid.");
                        }
                    } elseif (is_array($value)) {
                        // Enhanced format: ['mode' => 'mania', 'key_count' => 4]
                        if (! isset($value['mode'])) {
                            $fail("The {$attribute} must have a 'mode' key.");
                        }
                        if (! in_array($value['mode'], ['osu', 'taiko', 'catch', 'mania', 'fruits'])) {
                            $fail("The mode in {$attribute} is invalid.");
                        }
                    }
                },
            ],
            'vs_size' => 'nullable|integer|min:1|max:16',
            'team_size_min' => 'nullable|integer|min:1|max:16',
            'team_size_max' => 'nullable|integer|min:1|max:16|gte:team_size_min',
            'rank_range_min' => [
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $max = request()->input('rank_range_max');
                    if ($value && $max && $value > $max) {
                        $fail('Minimum rank must be less than or equal to maximum rank');
                    }
                },
            ],
            'rank_range_max' => 'nullable|integer|min:1',
            'registration_start' => 'nullable|date',
            'registration_end' => 'nullable|date|after_or_equal:registration_start',
            'tournament_start' => 'nullable|date',
            'tournament_end' => 'nullable|date|after_or_equal:tournament_start',
            'is_badge' => 'sometimes|boolean',
            'is_bws' => 'sometimes|boolean',
            'badge_urls' => 'nullable|array',
            'badge_urls.*' => 'nullable|url|max:2048',
            'badge_status' => 'nullable|in:approved,pending,rejected',
            'banner_url' => 'nullable|url|max:2048',
            'forum_post_url' => 'nullable|url|max:500',
            'spreadsheet_url' => 'nullable|url|max:500',
            'discord_url' => 'nullable|url|max:500',
            'twitch_url' => 'nullable|url|max:500',
            'format' => 'nullable|string|max:100',
            'team_formation_style' => 'nullable|string|in:standard,draft,auction,world_cup,suiji',
            'format_tags' => 'nullable|array',
            'format_tags.*' => 'required|string|in:draft,auction,world_cup,suiji,battle_royale',
            'format_structure' => 'nullable|array',
            'format_structure.stages' => 'nullable|array|max:8',
            'format_structure.stages.*.type' => 'nullable|string|in:qualifier,group_stage,swiss_round,bracket,battle_royale',
            'format_structure.stages.*.name' => 'nullable|string|max:100',
            'format_structure.stages.*.notes' => 'nullable|string|max:500',
            'format_structure.stages.*.entry_type' => 'nullable|string|in:winner_only,winner_loser_hybrid',
            'format_structure.stages.*.elimination_type' => 'nullable|string|in:single_elimination,double_elimination',
            'format_structure.stages.*.advance_count' => 'nullable|integer|min:1|max:4096',
            'format_structure.stages.*.round_count' => 'nullable|integer|min:1|max:64',
            'format_structure.stages.*.group_count' => 'nullable|integer|min:1|max:512',
            'format_structure.stages.*.teams_per_group' => 'nullable|integer|min:1|max:512',
            'format_structure.stages.*.start_round_size' => [
                'nullable',
                'integer',
                'min:2',
                'max:1024',
                'regex:/^(2|4|8|16|32|64|128|256|512|1024)$/',
            ],
            'format_structure.stages.*.lobby_count' => 'nullable|integer|min:1|max:512',
            'format_structure.stages.*.players_per_lobby' => 'nullable|integer|min:1|max:512',
            'format_structure.stages.*.advance_per_lobby' => 'nullable|integer|min:1|max:512',
            'format_structure.stages.*.eliminated_per_map' => 'nullable|integer|min:1|max:512',
            'format_structure.stages.*.elimination_rule' => 'nullable|string|max:100',
            'format_structure.stages.*.win_condition' => 'nullable|string|max:100',
            'registration_url' => 'nullable|url|max:2048',
            'bracket_url' => 'nullable|url|max:2048',
            'star_rating_first' => 'nullable|numeric|min:0|max:10',
            'star_rating_last' => 'nullable|numeric|min:0|max:10',
            'star_rating_qualifier' => 'nullable|numeric|min:0|max:10',
            // New fields
            'start_round_size' => [
                'nullable',
                'integer',
                'min:2',
                'max:1024',
                'regex:/^(2|4|8|16|32|64|128|256|512|1024)$/',
            ],
            'restricted_countries' => [
                'nullable',
                'array',
            ],
            'restricted_countries.*' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
                function ($attribute, $value, $fail) {
                    if (! app(CldrService::class)->isValidTerritoryCode((string) $value)) {
                        $fail("The {$attribute} must be a valid CLDR territory code.");
                    }
                },
            ],
            'mania_variants' => [
                'nullable',
                'array',
            ],
            'mania_variants.*' => [
                'required',
                'string',
                'in:mania_4k,mania_7k,mania_other',
            ],
            // BWS fields
            'bws_base_exponent' => 'nullable|numeric|between:0.9,1.0',
            'bws_badge_power' => 'nullable|numeric|between:1,5',
            'bws_divisor' => 'nullable|numeric|min:0.1|max:10',
            'bws_badge_age_cutoff' => 'nullable|date',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_round_size.regex' => 'The start round size must be a power of 2 (2, 4, 8, 16, 32, 64, 128, 256, 512, or 1024).',
            'restricted_countries.*.regex' => 'Country codes must be valid CLDR territory codes (e.g., KR, JP, US).',
            'restricted_countries.*.size' => 'Country codes must be exactly 2 characters.',
            'mania_variants.*.in' => 'Mania variants must be one of: 4K, 7K, or Other.',
        ];
    }
}
