<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireMessengerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::truncate();
        Conversation::truncate();
        Message::truncate();
        Cache::store('array')->flush();
    }

    /**
     * Unauthenticated user is redirected away from /chat.
     */
    public function test_guest_is_redirected_from_chat_page()
    {
        $this->get('/chat')->assertRedirect('/');
    }

    /**
     * Authenticated user can access the /chat page.
     */
    public function test_authenticated_user_can_access_chat_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/chat')->assertStatus(200);
    }

    /**
     * The Volt messenger component mounts successfully with auth user name.
     */
    public function test_messenger_component_mounts_with_user_name()
    {
        $user = User::factory()->create(['name' => 'Alice Volt']);
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->assertSet('profileName', 'Alice Volt');
    }

    /**
     * searchContact finds an existing user by tag and sets searchResult.
     */
    public function test_search_contact_finds_existing_user_by_tag()
    {
        $user = User::factory()->create(['user_tag' => 'alice#0001']);
        $target = User::factory()->create(['user_tag' => 'bob#0002', 'name' => 'Bob']);
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->set('searchUserTag', 'bob#0002')
            ->call('searchContact')
            ->assertSet('searchResult.name', 'Bob');
    }

    /**
     * searchContact sets searchResult to null when tag is not found and adds a validation error.
     */
    public function test_search_contact_returns_null_for_nonexistent_tag()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->set('searchUserTag', 'nobody#9999')
            ->call('searchContact')
            ->assertSet('searchResult', null)
            ->assertHasErrors('searchUserTag');
    }

    /**
     * addFriend dispatches friend-request-sent when searchResult and master_key are set.
     */
    public function test_add_friend_sends_friend_request_when_conditions_met()
    {
        $targetTag = 'sanco_1234567890';
        $user = User::factory()->create([
            'user_tag' => 'sanco_0987654321',
            'master_key' => 'encrypted_master_key',
        ]);
        $target = User::factory()->create(['user_tag' => $targetTag]);
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->set('searchUserTag', $targetTag)
            ->set('searchResult', $target)
            ->call('addFriend')
            ->assertDispatched('friend-request-sent');
    }

    /**
     * addFriend does nothing (early return) when user has no master_key.
     */
    public function test_add_friend_does_nothing_without_master_key()
    {
        $user = User::factory()->create(['master_key' => null]);
        $target = User::factory()->create();
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->set('searchResult', $target)
            ->call('addFriend')
            ->assertNotDispatched('friend-request-sent');
    }

    /**
     * selectConversation sets the selectedConversationId when a conversation ID is passed.
     */
    public function test_select_conversation_sets_active_conversation()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        Livewire::actingAs($user)
            ->test('messenger')
            ->call('selectConversation', (string) $conv->_id)
            ->assertSet('selectedConversationId', (string) $conv->_id);
    }

    /**
     * updateProfile dispatches profile-updated and persists the new name.
     */
    public function test_update_profile_saves_new_name()
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->set('profileName', 'New Name')
            ->call('updateProfile')
            ->assertDispatched('profile-updated');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
    }

    /**
     * generateNewKey returns a 24-word BIP39 mnemonic and updates master_key.
     */
    public function test_generate_new_key_updates_master_key()
    {
        $user = User::factory()->create(['master_key' => 'initial_master_key']);
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->call('generateNewKey');

        $user->refresh();
        $this->assertNotEquals('initial_master_key', $user->master_key);
    }

    /**
     * savePublicKey persists the new public key.
     */
    public function test_save_public_key_updates_public_key()
    {
        $user = User::factory()->create(['public_key' => 'old_key']);
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test('messenger')
            ->call('savePublicKey', 'new_synced_public_key');

        $user->refresh();
        $this->assertEquals('new_synced_public_key', $user->public_key);
    }
}
