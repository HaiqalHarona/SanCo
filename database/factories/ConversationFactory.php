<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'direct',   // direct | group
            'name' => null,       // group name (nullable for direct)
            'avatar' => null,     // group avatar (nullable)
            'participant_ids' => [], // array of User ObjectIds
            'last_message_id' => null,
            'last_activity_at' => now(),
            'created_by' => null, // User ObjectId
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the conversation is a group chat.
     */
    public function group(?string $name = null): static
    {
        return $this->state(function (array $attributes) use ($name) {
            $groupName = $name ?? $this->faker->words(3, true);
            return [
                'type' => 'group',
                'name' => ucwords($groupName),
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($groupName) . '&background=random&color=fff',
            ];
        });
    }
}
