<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::truncate();
        Conversation::truncate();
        Cache::store('array')->flush();
    }

    public function test_guest_cannot_list_conversations()
    {
        $this->getJson('/api/conversations')->assertStatus(401);
    }

    public function test_authenticated_user_gets_empty_inbox_initially()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/conversations');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertEmpty($response->json());
    }

    public function test_guest_cannot_create_conversation()
    {
        $this->postJson('/api/conversations', ['type' => 'direct', 'recipient_id' => 'abc'])->assertStatus(401);
    }

    public function test_user_can_create_direct_conversation()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/conversations', [
            'type' => 'direct',
            'recipient_id' => (string) $other->_id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    public function test_creating_direct_conversation_with_same_user_twice_returns_same_conversation()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/conversations', [
            'type' => 'direct',
            'recipient_id' => (string) $other->_id,
        ]);
        $second = $this->postJson('/api/conversations', [
            'type' => 'direct',
            'recipient_id' => (string) $other->_id,
        ]);

        $this->assertEquals($first->json('conversation_id'), $second->json('conversation_id'));
    }

    public function test_user_cannot_create_direct_conversation_with_themselves()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/conversations', [
            'type' => 'direct',
            'recipient_id' => (string) $user->_id,
        ])->assertStatus(400);
    }

    public function test_user_can_create_group_conversation()
    {
        $user = User::factory()->create();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/conversations', [
            'type' => 'group',
            'name' => 'Team Alpha',
            'participant_ids' => [
                (string) $p1->_id,
                (string) $p2->_id,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    public function test_user_can_view_conversation_details()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $this->getJson("/api/conversations/{$conv->_id}")
            ->assertStatus(200)
            ->assertJsonStructure(['id', 'type', 'participants', 'participant_ids']);
    }

    public function test_non_participant_cannot_view_conversation()
    {
        $user = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $a->_id, (string) $b->_id);

        $this->getJson("/api/conversations/{$conv->_id}")->assertStatus(404);
    }

    public function test_participant_can_add_another_user_to_group()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $newMember = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::create([
            'type' => 'group',
            'name' => 'Group Chat',
            'participant_ids' => [(string) $user->_id, (string) $other->_id],
            'created_by' => (string) $user->_id,
        ]);

        $this->postJson("/api/conversations/{$conv->_id}/participants", [
            'user_id' => (string) $newMember->_id,
        ])->assertStatus(200);

        $conv->refresh();
        $this->assertContains((string) $newMember->_id, $conv->participant_ids);
    }

    public function test_non_participant_cannot_add_to_group()
    {
        $user = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $newMember = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::create([
            'type' => 'group',
            'name' => 'Group Chat',
            'participant_ids' => [(string) $a->_id, (string) $b->_id],
            'created_by' => (string) $a->_id,
        ]);

        $this->postJson("/api/conversations/{$conv->_id}/participants", [
            'user_id' => (string) $newMember->_id,
        ])->assertStatus(404);
    }

    public function test_participant_can_remove_another_user_from_group()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = Conversation::create([
            'type' => 'group',
            'name' => 'Group Chat',
            'participant_ids' => [(string) $user->_id, (string) $other->_id],
            'created_by' => (string) $user->_id,
        ]);

        $this->deleteJson("/api/conversations/{$conv->_id}/participants/{$other->_id}")
            ->assertStatus(200);

        $conv->refresh();
        $this->assertNotContains((string) $other->_id, $conv->participant_ids);
    }

    public function test_inbox_appears_after_direct_conversation_is_created()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/conversations', [
            'type' => 'direct',
            'recipient_id' => (string) $other->_id,
        ]);

        $inbox = $this->getJson('/api/conversations')->json();
        $this->assertCount(1, $inbox);
    }
}
