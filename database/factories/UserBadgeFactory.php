<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBadge>
 */
class UserBadgeFactory extends Factory
{
    protected $model = UserBadge::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Tournament Winner',
            'badge_url' => 'https://osu.ppy.sh/community/forums/topics/'.fake()->numberBetween(1000000, 9999999),
            'image_url' => 'https://assets.ppy.sh/badges/'.fake()->uuid().'.png',
            'image_2x_url' => 'https://assets.ppy.sh/badges/'.fake()->uuid().'@2x.png',
            'awarded_at' => fake()->dateTimeBetween('-5 years', 'now'),
            'is_bws_eligible' => true,
        ];
    }

    /**
     * Badge is not BWS eligible
     */
    public function notBwsEligible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bws_eligible' => false,
        ]);
    }
}
