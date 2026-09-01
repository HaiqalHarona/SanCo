<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::truncate();
        Friendship::truncate();
        Conversation::truncate();
        Cache::store('array')->flush();
    }

    // ─── LIST FRIENDS ─────────────────────────────────────────────────────────

    public function test_guest_cannot_list_friends()
    {
        $this->getJson('/api/friends')->assertStatus(401);
    }

    public function test_user_gets_empty_friends_list_initially()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/friends');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertEmpty($response->json());
    }

    // ─── SEND FRIEND REQUEST ──────────────────────────────────────────────────

    public function test_guest_cannot_send_friend_request()
    {
        $this->postJson('/api/friends/requests', ['friend_id' => 'x'])->assertStatus(401);
    }

    public function test_user_can_send_friend_request()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/friends/requests', ['friend_id' => (string) $other->_id])
            ->assertStatus(201);

        $this->assertTrue(
            Friendship::where('user_id', (string) $user->_id)
                ->where('friend_id', (string) $other->_id)
                ->where('status', 'pending')
                ->exists()
        );
    }

    public function test_sending_duplicate_friend_request_is_idempotent()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/friends/requests', ['friend_id' => (string) $other->_id]);
        $second = $this->postJson('/api/friends/requests', ['friend_id' => (string) $other->_id]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertEquals($first->json('friendship_id'), $second->json('friendship_id'));
        $this->assertEquals(
            1,
            Friendship::where('user_id', (string) $user->_id)
                ->where('friend_id', (string) $other->_id)
                ->count()
        );
    }

    public function test_user_cannot_send_friend_request_to_themselves()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/friends/requests', ['friend_id' => (string) $user->_id])
            ->assertStatus(400);
    }

    public function test_user_cannot_send_friend_request_to_blocked_user()
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        // Target blocks user
        Sanctum::actingAs($target);
        $this->postJson("/api/friends/{$user->_id}/block");

        // Then user tries to send friend request to target -> should fail 400
        Sanctum::actingAs($user);
        $this->postJson('/api/friends/requests', ['friend_id' => (string) $target->_id])
            ->assertStatus(400);
    }

    // ─── ACCEPT FRIEND REQUEST ─────────────────────────────────────────────────

    public function test_user_can_accept_friend_request()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        // Sender sends request
        Sanctum::actingAs($sender);
        $this->postJson('/api/friends/requests', ['friend_id' => (string) $receiver->_id]);

        // Receiver accepts
        Sanctum::actingAs($receiver);
        $this->putJson("/api/friends/requests/{$sender->_id}/accept")
            ->assertStatus(200);

        $this->assertTrue(Friendship::areFriends((string) $sender->_id, (string) $receiver->_id));
    }

    // ─── REJECT FRIEND REQUEST ─────────────────────────────────────────────────

    public function test_user_can_reject_friend_request()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender);
        $this->postJson('/api/friends/requests', ['friend_id' => (string) $receiver->_id]);

        Sanctum::actingAs($receiver);
        $this->deleteJson("/api/friends/requests/{$sender->_id}/reject")
            ->assertStatus(200);

        $this->assertFalse(Friendship::areFriends((string) $sender->_id, (string) $receiver->_id));
    }

    // ─── UNFRIEND ─────────────────────────────────────────────────────────────

    public function test_user_can_unfriend()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/friends/requests', ['friend_id' => (string) $friend->_id]);

        Sanctum::actingAs($friend);
        $this->putJson("/api/friends/requests/{$user->_id}/accept");

        Sanctum::actingAs($user);
        $this->deleteJson("/api/friends/{$friend->_id}")->assertStatus(200);

        $this->assertFalse(Friendship::areFriends((string) $user->_id, (string) $friend->_id));
    }

    // ─── PENDING REQUESTS ─────────────────────────────────────────────────────

    public function test_user_can_view_pending_requests()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender);
        $this->postJson('/api/friends/requests', ['friend_id' => (string) $receiver->_id]);

        Sanctum::actingAs($receiver);
        $response = $this->getJson('/api/friends/requests/pending');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertCount(1, $response->json());
    }

    // ─── BLOCK / UNBLOCK ──────────────────────────────────────────────────────

    public function test_guest_cannot_block_user()
    {
        $this->postJson('/api/friends/xyz/block')->assertStatus(401);
    }

    public function test_user_can_block_another_user()
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/friends/{$target->_id}/block")->assertStatus(200);

        $this->assertTrue(Friendship::hasBlocked((string) $user->_id, (string) $target->_id));
    }

    public function test_guest_cannot_unblock_user()
    {
        $this->deleteJson('/api/friends/xyz/unblock')->assertStatus(401);
    }

    public function test_user_can_unblock_another_user()
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/friends/{$target->_id}/block");
        $this->deleteJson("/api/friends/{$target->_id}/unblock")->assertStatus(200);

        $this->assertFalse(Friendship::hasBlocked((string) $user->_id, (string) $target->_id));
    }

    public function test_friends_list_updates_after_accepting_request()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/friends/requests', ['friend_id' => (string) $friend->_id]);

        Sanctum::actingAs($friend);
        $this->putJson("/api/friends/requests/{$user->_id}/accept");

        $response = $this->getJson('/api/friends');
        $this->assertCount(1, $response->json());
    }
}
