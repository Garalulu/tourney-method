<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ParticipationRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $updating = $this->route('record') !== null;

        return [
            'tournament_id' => [
                $updating ? 'sometimes' : 'required',
                'integer',
                Rule::exists('tournaments', 'id')->where('status', 'approved'),
            ],
            'stage_value' => ['nullable', 'string', 'max:80'],
            'placement_override' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'placement_range_override' => ['nullable', 'string', 'max:20'],
            'seed' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'team_name' => ['nullable', 'string', 'max:150'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'teammates_submitted' => ['nullable', 'boolean'],
            'teammate_ids' => ['nullable', 'array', 'max:50'],
            'teammate_ids.*' => ['nullable', 'integer', 'distinct', 'exists:users,id'],
            'pending_teammate_osu_ids' => ['nullable', 'array', 'max:50'],
            'pending_teammate_osu_ids.*' => ['nullable', 'integer', 'distinct', 'min:1'],
            'matches' => ['nullable', 'array', 'max:40'],
            'matches.*.stage' => ['nullable', 'string', 'max:80'],
            'matches.*.result' => ['nullable', 'string', 'max:40'],
            'matches.*.score_for' => ['nullable', 'integer', 'min:-1', 'max:9'],
            'matches.*.score_against' => ['nullable', 'integer', 'min:-1', 'max:9'],
            'matches.*.mp_link' => ['nullable', 'string', 'max:255', 'regex:/^(?:\d+|https?:\/\/osu\.ppy\.sh\/(?:community\/matches|mp)\/\d+)$/'],
            'matches.*.mp_id' => ['nullable', 'integer', 'min:1'],
            'matches.*.is_forfeit' => ['nullable', 'boolean'],
            'matches.*.is_individual_qualifier' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('matches', []) as $index => $match) {
                if (! is_array($match)) {
                    continue;
                }

                $hasMatchData = collect([
                    $match['result'] ?? null,
                    $match['score_for'] ?? null,
                    $match['score_against'] ?? null,
                    $match['mp_link'] ?? null,
                    $match['mp_id'] ?? null,
                ])->contains(fn ($value): bool => $value !== null && $value !== '');

                if (($hasMatchData || (bool) ($match['is_forfeit'] ?? false))
                    && trim((string) ($match['stage'] ?? '')) === '') {
                    $validator->errors()->add(
                        "matches.{$index}.stage",
                        'Choose a stage before saving this match.',
                    );
                }
            }
        });
    }
}
