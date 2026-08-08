<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => null, // set in seeder
            'sender_id' => null,       // set in seeder
            'type' => 'text',          // text | image | file | audio | video | system
            'body' => $this->faker->sentence(),
            'read_by' => [],           // array of { user_id, read_at }
            'reactions' => [],         // array of { user_id, emoji }
            'reply_to_id' => null,
            'is_edited' => false,
            'edited_at' => null,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the message has an embedded attachment.
     */
    public function withAttachment(): static
    {
        return $this->state(function (array $attributes) {
            $types = ['image/jpeg', 'application/pdf', 'audio/mpeg', 'video/mp4'];
            $mime = $this->faker->randomElement($types);
            $exts = [
                'image/jpeg' => 'jpg',
                'application/pdf' => 'pdf',
                'audio/mpeg' => 'mp3',
                'video/mp4' => 'mp4'
            ];
            $type = explode('/', $mime)[0];
            if ($type === 'application') {
                $type = 'file';
            }

            return [
                'type' => $type,
                'attachments' => [
                    [
                        'file_name' => $this->faker->word() . '.' . $exts[$mime],
                        'file_size' => $this->faker->numberBetween(1024, 10485760),
                        'mime_type' => $mime,
                        'url' => 'https://picsum.photos/800/600',
                        'thumbnail_url' => $type === 'image' ? 'https://picsum.photos/200/200' : null,
                        'duration' => in_array($type, ['audio', 'video']) ? $this->faker->numberBetween(10, 300) : null,
                        'width' => in_array($type, ['image', 'video']) ? 1280 : null,
                        'height' => in_array($type, ['image', 'video']) ? 720 : null,
                    ]
                ]
            ];
        });
    }
}
