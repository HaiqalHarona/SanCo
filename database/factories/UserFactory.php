<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $hashedMasterKey = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isGoogle = $this->faker->boolean(50);
        $name = $this->faker->name();

        return [
            'name' => $name,
            'email' => $this->faker->unique()->safeEmail(),
            'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=random&color=fff',
            'status' => $this->faker->randomElement(['online', 'offline', 'away']),
            'last_seen_at' => $this->faker->dateTimeThisMonth(),
            'google_id' => $isGoogle ? $this->faker->numerify('#####################') : null,
            'github_id' => !$isGoogle ? $this->faker->numerify('########') : null,
            'user_tag' => 'sanco_' . Str::lower(Str::random(10)),
            // Seed a hashed master key (bcrypt of a dummy BIP39 phrase or password)
            'master_key' => self::$hashedMasterKey ??= bcrypt('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about'),
            'public_key' => 'fake_public_key_' . Str::random(40),
            'current_session_id' => null,
            'last_login_ip' => $this->faker->ipv4(),
            'last_login_browser' => $this->faker->userAgent(),
            'last_login_location' => $this->faker->city() . ', ' . $this->faker->country(),
        ];
    }

    /**
     * Indicate that the user is online.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is offline.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'offline',
            'last_seen_at' => $this->faker->dateTimeThisMonth(),
        ]);
    }
}
