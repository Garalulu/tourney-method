<?php

namespace App\Livewire;

use App\Jobs\ImportMatchJob;
use App\Models\OsuMatch;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class MatchSubmission extends Component
{
    public string $mpLink = '';

    public ?int $tournamentId = null;

    public bool $loading = false;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'mpLink' => [
                'required',
                'url',
                'regex:/^https?:\/\/osu\.ppy\.sh\/(?:community\/matches|mp)\/\d+$/',
                function (string $attribute, string $value, \Closure $fail): void {
                    // Extract match ID and check for duplication
                    preg_match('~(?:matches|mp)/(\d+)~', $value, $matches);

                    if (empty($matches[1])) {
                        $fail('Invalid multiplayer link format. Use: https://osu.ppy.sh/community/matches/123456 or https://osu.ppy.sh/mp/123456');

                        return;
                    }

                    $matchId = (int) $matches[1];

                    // Check if this match has already been imported
                    if (OsuMatch::where('osu_match_id', $matchId)->exists()) {
                        $fail('This match has already been imported.');

                        return;
                    }
                },
            ],
            'tournamentId' => [
                'nullable',
                'exists:tournaments,id',
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @var array<string, string>
     */
    protected array $messages = [
        'mpLink.required' => 'Please provide a multiplayer link.',
        'mpLink.url' => 'The multiplayer link must be a valid URL.',
        'mpLink.regex' => 'Invalid multiplayer link format. Use: https://osu.ppy.sh/community/matches/123456 or https://osu.ppy.sh/mp/123456',
        'tournamentId.exists' => 'The selected tournament does not exist.',
    ];

    /**
     * Submit the match for import.
     */
    public function submit(): void
    {
        $this->loading = true;
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            // Validate input
            $this->validate();

            // Extract match ID
            preg_match('~(?:matches|mp)/(\d+)~', $this->mpLink, $matches);
            $matchId = (int) $matches[1];

            // Dispatch import job
            ImportMatchJob::dispatch($matchId, Auth::id(), $this->tournamentId);

            // Reset form
            $this->mpLink = '';
            $this->tournamentId = null;

            $this->successMessage = 'Match import started! Please check back in a moment.';
        } catch (ValidationException $e) {
            $this->errorMessage = $e->validator->errors()->first();
        } catch (\Exception $e) {
            $this->errorMessage = 'An error occurred while submitting the match. Please try again.';
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $tournaments = Tournament::where('status', 'approved')
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('livewire.match-submission', [
            'tournaments' => $tournaments,
        ]);
    }
}
