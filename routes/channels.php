<?php

use App\Broadcasting\UserPresence;
use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('presence.chat', UserPresence::class);

Broadcast::channel('message.{conversationId}', function ($user, $conversationId) {
    return Conversation::where('_id', $conversationId)
        ->where('participant_ids', (string) $user->_id)
        ->exists();
});

Broadcast::channel('user.{userId}', function ($user, $id) {
    return (string) $user->_id === $id;
});
