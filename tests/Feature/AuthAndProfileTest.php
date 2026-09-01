<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::truncate();
        Conversation::truncate();
        Cache::store('array')->flush();
        Storage::fake('public');
    }

    public function test_guest_cannot_access_user_profile()
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_authenticated_user_can_retrieve_profile()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'public_key' => 'rsa_public_key_xyz',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email', 'avatar', 'public_key'])
            ->assertJsonPath('name', 'Test User')
            ->assertJsonPath('email', 'test@example.com')
            ->assertJsonPath('public_key', 'rsa_public_key_xyz');
    }

    public function test_guest_cannot_update_profile()
    {
        $this->putJson('/api/user/profile', ['name' => 'Hacker'])->assertStatus(401);
    }

    public function test_user_can_update_profile_name()
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/profile', ['name' => 'New Name']);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'New Name');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
    }

    public function test_profile_update_requires_name()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/user/profile', [])->assertStatus(422);
    }

    public function test_user_can_upload_base64_avatar()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Minimal 1×1 transparent PNG as base64
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->putJson('/api/user/profile', [
            'name' => $user->name,
            'avatar_base64' => $png,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('storage/avatars/', $response->json('user.avatar'));
    }

    public function test_invalid_avatar_format_returns_400()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/profile', [
            'name' => $user->name,
            'avatar_base64' => 'not_valid_base64_image',
        ]);

        $response->assertStatus(400);
    }

    public function test_guest_cannot_sync_public_key()
    {
        $this->postJson('/api/user/keys/sync', ['public_key' => 'key'])->assertStatus(401);
    }

    public function test_user_can_sync_public_key()
    {
        $user = User::factory()->create(['public_key' => 'old_key']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user/keys/sync', [
            'public_key' => 'new_rsa_public_key_abc',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('public_key', 'new_rsa_public_key_abc');

        $user->refresh();
        $this->assertEquals('new_rsa_public_key_abc', $user->public_key);
    }

    public function test_sync_public_key_requires_public_key_field()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/user/keys/sync', [])->assertStatus(422);
    }

    public function test_dev_login_returns_401_when_disabled()
    {
        config(['app.allow_dev_login' => false]);

        $user = User::factory()->create();
        $this->postJson("/api/dev-login/{$user->_id}")->assertStatus(404);
    }
}
