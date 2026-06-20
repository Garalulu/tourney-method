<?php

namespace App\Domain\Participation;

use App\Models\MatchScore;
use Illuminate\Database\Eloquent\Collection;

class MatchWinCalculator
{
    /**
     * @param  Collection<int, MatchScore>  $userScores
     */
    public function count(Collection $userScores): int
    {
        return $userScores
            ->loadMissing('game.scores')
            ->filter(fn (MatchScore $userScore): bool => $this->isWinningScore($userScore))
            ->count();
    }

    private function isWinningScore(MatchScore $userScore): bool
    {
        $scores = $userScore->game->scores;

        if ($userScore->team !== null && $userScore->team !== 'none') {
            $teamTotals = $scores
                ->filter(fn (MatchScore $score): bool => $score->team !== null && $score->team !== 'none')
                ->groupBy('team')
                ->map(fn ($teamScores): int => (int) $teamScores->sum('score'))
                ->sortDesc()
                ->values();

            return $teamTotals->count() > 1
                && (int) $teamTotals->first() > (int) $teamTotals->get(1)
                && (int) $scores->where('team', $userScore->team)->sum('score') === (int) $teamTotals->first();
        }

        $scoresByValue = $scores->sortByDesc('score')->values();

        return $scoresByValue->count() > 1
            && (int) $scoresByValue->first()->score > (int) $scoresByValue->get(1)->score
            && $scoresByValue->first()->is($userScore);
    }
}
