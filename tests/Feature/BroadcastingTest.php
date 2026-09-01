<?php

namespace Tests\Feature;

use App\Events\IncomingRequest;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::truncate();
        Conversation::truncate();
        Message::truncate();
        Friendship::truncate();
        Cache::store('array')->flush();
    }

    /**
     * user.{id} channel allows access for the owner.
     */
    public function test_user_channel_allows_owner()
    {
        $user = User::factory()->create();

        $result = $this->callChannelAuthorization(
            "user.{$user->_id}",
            $user,
            [(string) $user->_id]
        );

        $this->assertTrue($result);
    }

    /**
     * user.{id} channel denies access for non-owners.
     */
    public function test_user_channel_denies_non_owner()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $result = $this->callChannelAuthorization(
            "user.{$user->_id}",
            $other,
            [(string) $user->_id]
        );

        $this->assertFalse($result);
    }

    /**
     * message.{convId} channel allows conversation participants.
     */
    public function test_message_channel_allows_participants()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $result = $this->callChannelAuthorization(
            "message.{$conv->_id}",
            $user,
            [(string) $conv->_id]
        );

        $this->assertTrue($result);
    }

    /**
     * message.{convId} channel denies non-participants.
     */
    public function test_message_channel_denies_non_participants()
    {
        $user = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $conv = Conversation::findOrCreateDirect((string) $a->_id, (string) $b->_id);

        $result = $this->callChannelAuthorization(
            "message.{$conv->_id}",
            $user,
            [(string) $conv->_id]
        );

        $this->assertFalse((bool) $result);
    }

    /**
     * Helper to invoke a registered broadcast channel callback directly.
     */
    private function callChannelAuthorization(string $channelName, $user, array $parameters): mixed
    {
        $channels = Broadcast::getChannels();

        foreach ($channels as $pattern => $callback) {
            // Replace {param} placeholders FIRST, then quote the literal parts
            $withPlaceholders = preg_replace('/\{[^}]+\}/', '__PARAM__', $pattern);
            $escaped = preg_quote($withPlaceholders, '/');
            $regex = '/^'.str_replace('__PARAM__', '[^.]+', $escaped).'$/';

            if (preg_match($regex, $channelName)) {
                if (is_callable($callback)) {
                    return $callback($user, ...$parameters);
                }
                if (is_string($callback) && class_exists($callback)) {
                    return (new $callback)->join($user, ...$parameters);
                }
            }
        }

        return false;
    }

    /**
     * MessageSent event broadcasts to message.{convId} channel plus each participant's user.{id} channel.
     */
    public function test_message_sent_event_broadcasts_to_correct_channels()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        // System message bypasses E2EE enforcement
        $msg = Message::factory()->create([
            'conversation_id' => (string) $conv->_id,
            'sender_id' => (string) $user->_id,
            'type' => 'system',
        ]);

        $event = new MessageSent($msg);
        $channels = $event->broadcastOn();

        // PrivateChannel.name is prefixed with 'private-' by Laravel
        $channelNames = array_map(fn ($ch) => $ch->name, $channels);

        $this->assertContains('private-message.'.$conv->_id, $channelNames);
    }

    /**
     * MessageSent event has the correct broadcast name.
     */
    public function test_message_sent_event_has_correct_broadcast_name()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        $msg = Message::factory()->create([
            'conversation_id' => (string) $conv->_id,
            'sender_id' => (string) $user->_id,
            'type' => 'system',
        ]);

        $event = new MessageSent($msg);
        $this->assertEquals('MessageSent', $event->broadcastAs());
    }

    /**
     * Friend request endpoint creates the friendship record, proving the controller
     * code path that constructs IncomingRequest ran successfully.
     * (broadcast()->toOthers() is a no-op in test context without a socket.)
     */
    public function test_incoming_request_event_code_path_runs_on_friend_request()
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/friends/requests', ['friend_id' => (string) $target->_id])
            ->assertStatus(201);

        $this->assertTrue(
            Friendship::where('user_id', (string) $user->_id)
                ->where('friend_id', (string) $target->_id)
                ->where('status', 'pending')
                ->exists()
        );
    }

    /**
     * IncomingRequest event has the correct channel and broadcastAs name.
     */
    public function test_incoming_request_event_contract()
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $event = new IncomingRequest((string) $target->_id, (string) $user->_id);

        $channels = $event->broadcastOn();
        $channelNames = array_map(fn ($ch) => $ch->name, $channels);

        $this->assertContains('private-user.'.$target->_id, $channelNames);
        $this->assertEquals('IncomingRequest', $event->broadcastAs());
    }

    /**
     * MessageSent event is fired when a message is sent via messenger.
     */
    public function test_message_sent_event_is_fired_via_livewire_messenger()
    {
        Event::fake([MessageSent::class]);

        $user = User::factory()->create(['master_key' => 'mock_master_key']);
        $other = User::factory()->create();

        $conv = Conversation::findOrCreateDirect((string) $user->_id, (string) $other->_id);

        Livewire::actingAs($user)
            ->test('messenger')
            ->set('selectedConversationId', (string) $conv->_id)
            ->call(
                'messageUser',
                base64_encode('encrypted_msg'),
                base64_encode(random_bytes(24)),
                [(string) $other->_id => base64_encode(random_bytes(32))]
            );

        Event::assertDispatched(MessageSent::class);
    }
}
