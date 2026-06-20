<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentCorrection>
 */
class TournamentCorrectionFactory extends Factory
{
    protected $model = TournamentCorrection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'submitted_by' => User::factory(),
            'status' => TournamentCorrection::STATUS_PENDING,
            'kind' => TournamentCorrection::KIND_CORRECTION,
            'payload' => [
                'changes' => [
                    'metadata.title' => [
                        'domain' => 'metadata',
                        'field' => 'title',
                        'label' => 'Title',
                        'old' => 'Old title',
                        'new' => 'New title',
                    ],
                ],
            ],
            'current_snapshot' => [],
            'submitter_note' => null,
            'admin_decisions' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }
}
