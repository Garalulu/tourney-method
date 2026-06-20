<?php

namespace Database\Factories;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modes = ['osu', 'taiko', 'catch', 'mania'];
        $selectedModes = $this->faker->randomElements($modes, $this->faker->numberBetween(1, 2));

        // Generate realistic rank ranges
        $rankRanges = [
            [1, 999],
            [1000, 4999],
            [5000, 9999],
            [10000, 49999],
            [50000, 99999],
            [100000, 500000],
        ];
        $rankRange = $this->faker->randomElement($rankRanges);

        // Registration dates: start = now + 1 week, end = start + 2 weeks
        $registrationStart = $this->faker->dateTimeBetween('now', '+1 week');
        $regEndDate = (clone $registrationStart)->modify('+2 weeks');
        $registrationEnd = $this->faker->dateTimeBetween($registrationStart, $regEndDate);

        // Tournament dates: start = reg end + 1 week, end = start + 1 month
        $tournStartDate = (clone $registrationEnd)->modify('+1 week');
        $tournamentStart = $this->faker->dateTimeBetween($registrationEnd, $tournStartDate);
        $tournEndDate = (clone $tournamentStart)->modify('+1 month');
        $tournamentEnd = $this->faker->dateTimeBetween($tournamentStart, $tournEndDate);

        // Badge tournaments are 30% probability
        $isBadge = $this->faker->boolean(30);

        // BWS is 50% probability if badge tournament
        $isBws = $isBadge && $this->faker->boolean(50);

        // Common tournament name patterns
        $prefixes = [
            'osu!',
            'Community',
            'International',
            'Global',
            'Open',
            'Expert',
            'Casual',
            'Elite',
            'Rising Stars',
            'Beginner\'s',
        ];

        $suffixes = [
            'Tournament',
            'Cup',
            'Championship',
            'Challenge',
            'Showdown',
            'Battle',
            'Series',
            'Competition',
            'League',
            'Festival',
        ];

        $title = $this->faker->randomElement($prefixes).' '.
                 ucfirst($this->faker->randomElement($selectedModes)).' '.
                 $this->faker->randomElement($suffixes).' '.
                 $this->faker->year();

        return [
            'forum_topic_id' => $this->faker->optional(0.7)->numberBetween(1000000, 9999999),
            // forum_post_url removed - now computed via accessor from forum_topic_id
            'title' => $title,
            'description' => $this->faker->paragraphs(3, true),
            'host_osu_id' => $this->faker->numberBetween(1000, 999999),
            'host_username' => $this->faker->userName(),
            'status' => 'approved', // Default to approved for testing
            'modes' => $selectedModes,
            'team_size_min' => $this->faker->optional()->numberBetween(1, 4),
            'team_size_max' => $this->faker->optional()->numberBetween(2, 8),
            'registration_start' => $registrationStart,
            'registration_end' => $registrationEnd,
            'tournament_start' => $tournamentStart,
            'tournament_end' => $tournamentEnd,
            'rank_range_min' => $rankRange[0],
            'rank_range_max' => $rankRange[1],
            'is_badge' => $isBadge,
            'is_bws' => $isBws,
            'bws_base_exponent' => $isBws ? $this->faker->randomFloat(4, 0.9900, 0.9950) : 0.9937,
            'bws_badge_power' => $isBws ? $this->faker->randomFloat(2, 1.5, 2.5) : 2.0,
            'bws_badge_age_cutoff' => $isBws ? $this->faker->optional()->dateTimeBetween('-1 year', 'now') : null,
            'star_rating_min' => $this->faker->optional(0.5)->randomFloat(2, 1.0, 4.0),
            'star_rating_max' => $this->faker->optional(0.5)->randomFloat(2, 5.0, 8.0),
            'format' => $this->faker->randomElement([
                '1v1',
                '2v2',
                '3v3',
                '4v4',
                'Team VS',
                'Battle Royale',
                'Double Elimination',
                'Single Elimination',
                'Swiss',
                'Round Robin',
            ]),
            'banner_url' => $this->faker->optional(0.6)->imageUrl(1200, 300, 'sports', true),
            'discord_url' => $this->faker->optional(0.8)->url(),
            'twitch_url' => $this->faker->optional(0.4)->url(),
            'spreadsheet_url' => $this->faker->optional(0.7)->url(),
            'bracket_url' => $this->faker->optional(0.6)->url(),
            'registration_url' => $this->faker->optional(0.8)->url(),
            'tcomm_url' => $this->faker->optional(0.3)->url(),
            'tcomm_id' => null,
            'otr_id' => null,
            'import_source' => 'manual',
            'rejection_reason' => null,
            'parsed_at' => null,
            'imported_at' => null,
            'reviewed_at' => now(),
            'reviewed_by' => null,
        ];
    }

    /**
     * Indicate that the tournament is pending review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_review',
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);
    }

    /**
     * Indicate that the tournament is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => $this->faker->sentence(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the tournament is a badge tournament.
     */
    public function badge(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_badge' => true,
        ]);
    }

    /**
     * Indicate that the tournament uses BWS.
     */
    public function bws(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bws' => true,
            'bws_base_exponent' => $this->faker->randomFloat(4, 0.9900, 0.9950),
            'bws_badge_power' => $this->faker->randomFloat(2, 1.5, 2.5),
        ]);
    }

    /**
     * Indicate that the tournament has ended.
     */
    public function ended(): static
    {
        $registrationStart = $this->faker->dateTimeBetween('-6 months', '-3 months');
        $registrationEnd = $this->faker->dateTimeBetween($registrationStart, '+2 weeks');
        $tournamentStart = $this->faker->dateTimeBetween($registrationEnd, '+1 week');
        $tournamentEnd = $this->faker->dateTimeBetween($tournamentStart, '-1 day');

        return $this->state(fn (array $attributes) => [
            'registration_start' => $registrationStart,
            'registration_end' => $registrationEnd,
            'tournament_start' => $tournamentStart,
            'tournament_end' => $tournamentEnd,
        ]);
    }

    /**
     * Indicate that the tournament is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the tournament is currently active.
     */
    public function active(): static
    {
        $registrationStart = $this->faker->dateTimeBetween('-2 weeks', '-1 week');
        $registrationEnd = $this->faker->dateTimeBetween($registrationStart, '+1 week');
        $tournamentStart = $this->faker->dateTimeBetween($registrationEnd, 'now');
        $tournamentEnd = $this->faker->dateTimeBetween('+1 week', '+1 month');

        return $this->state(fn (array $attributes) => [
            'registration_start' => $registrationStart,
            'registration_end' => $registrationEnd,
            'tournament_start' => $tournamentStart,
            'tournament_end' => $tournamentEnd,
        ]);
    }

    /**
     * Indicate that the tournament has open registration.
     */
    public function registrationOpen(): static
    {
        $registrationStart = $this->faker->dateTimeBetween('-1 week', 'now');
        $registrationEnd = $this->faker->dateTimeBetween('+1 week', '+2 weeks');
        $tournamentStart = $this->faker->dateTimeBetween($registrationEnd, '+1 week');
        $tournamentEnd = $this->faker->dateTimeBetween($tournamentStart, '+1 month');

        return $this->state(fn (array $attributes) => [
            'registration_start' => $registrationStart,
            'registration_end' => $registrationEnd,
            'tournament_start' => $tournamentStart,
            'tournament_end' => $tournamentEnd,
        ]);
    }
}
