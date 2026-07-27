<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversationId;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
        $this->conversationId = (string) $message->conversation_id;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('message.' . $this->conversationId),
        ];

        if ($this->message && $this->message->conversation) {
            foreach ($this->message->conversation->participant_ids ?? [] as $participantId) {
                $channels[] = new PrivateChannel('user.' . (string) $participantId);
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }
}
