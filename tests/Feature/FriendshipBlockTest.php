<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Friendship;
use Laravel\Sanctum\Sanctum;

class FriendshipBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear database before each test
        User::truncate();
        Friendship::truncate();
    }

    public function test_guest_cannot_block_user()
    {
        $response = $this->postJson('/api/friends/12345/block');
        $response->assertStatus(401);
    }

    public function test_guest_cannot_unblock_user()
    {
        $response = $this->deleteJson('/api/friends/12345/unblock');
        $response->assertStatus(401);
    }

    public function test_user_can_block_friend()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        // Establish a friendship first
        Friendship::create([
            'user_id' => $user->_id,
            'friend_id' => $friend->_id,
            'status' => 'accepted',
            'action_user_id' => $user->_id,
            'accepted_at' => now(),
        ]);
        Friendship::create([
            'user_id' => $friend->_id,
            'friend_id' => $user->_id,
            'status' => 'accepted',
            'action_user_id' => $user->_id,
            'accepted_at' => now(),
        ]);

        $this->assertTrue(Friendship::areFriends($user->_id, $friend->_id));

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/friends/{$friend->_id}/block");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'User blocked.']);

        // Assert friendship records are deleted, and a block record is created
        $this->assertFalse(Friendship::areFriends($user->_id, $friend->_id));
        $this->assertTrue(Friendship::hasBlocked($user->_id, $friend->_id));
        $this->assertFalse(Friendship::hasBlocked($friend->_id, $user->_id));
    }

    public function test_user_can_unblock_user()
    {
        $user = User::factory()->create();
        $blocked = User::factory()->create();

        // Create blocked friendship record
        Friendship::create([
            'user_id' => $user->_id,
            'friend_id' => $blocked->_id,
            'status' => 'blocked',
            'action_user_id' => $user->_id,
            'blocked_at' => now(),
        ]);

        $this->assertTrue(Friendship::hasBlocked($user->_id, $blocked->_id));

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/friends/{$blocked->_id}/unblock");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'User unblocked.']);

        // Assert block record is deleted
        $this->assertFalse(Friendship::hasBlocked($user->_id, $blocked->_id));
    }
}
