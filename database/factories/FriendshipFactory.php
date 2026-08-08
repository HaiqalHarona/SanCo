<?php

namespace Database\Factories;

use App\Models\Friendship;
use Illuminate\Database\Eloquent\Factories\Factory;

class FriendshipFactory extends Factory
{
    protected $model = Friendship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,       // set in seeder
            'friend_id' => null,     // set in seeder
            'status' => 'pending',   // pending | accepted | blocked
            'action_user_id' => null, // set in seeder
            'blocked_at' => null,
            'accepted_at' => null,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the friendship is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that a user is blocked.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
            'blocked_at' => now(),
        ]);
    }
}
