<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class MessageService
{
    // ── Reads ──────────────────────────────────────────────────

    /**
     * Fetch paginated messages. Only page 1 is cached to prevent
     * unbounded Redis memory usage from large conversation histories.
     */
    public function getMessages(string $convId, int $page = 1, int $limit = 20): LengthAwarePaginator
    {
        if ($page === 1) {
            // We cache page 1 as serialized paginator
            $cacheKey = "sanco:conv:{$convId}:latest_messages";
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $paginator = Message::where('conversation_id', $convId)
            ->with('attachments')
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);

        if ($page === 1) {
            Cache::put("sanco:conv:{$convId}:latest_messages", $paginator, now()->addHour());
        }

        return $paginator;
    }

    // ── Writes ─────────────────────────────────────────────────

    public function send(array $data): Message
    {
        $type = $data['type'] ?? 'text';

        // Enforce E2EE for all user-sent messages (non-system messages)
        if ($type !== 'system') {
            $metadata = $data['metadata'] ?? [];
            $isEncrypted = $metadata['is_encrypted'] ?? false;
            $nonce = $metadata['nonce'] ?? null;
            $encKeys = $metadata['enc_keys'] ?? null;

            if (!$isEncrypted || empty($nonce) || empty($encKeys)) {
                throw new \InvalidArgumentException('Message body must be end-to-end encrypted (E2EE). Plaintext is rejected.');
            }
        }

        $message = Message::create([
            'conversation_id' => $data['conversation_id'],
            'sender_id'       => $data['sender_id'],
            'type'            => $type,
            'body'            => $data['body'] ?? '',
            'read_by'         => [
                [
                    'user_id' => $data['sender_id'],
                    'read_at' => now()->toISOString(),
                ]
            ],
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'metadata'    => $data['metadata'] ?? [],
        ]);

        // Update the conversation's last activity
        Conversation::where('_id', $data['conversation_id'])->update([
            'last_message_id'  => $message->_id,
            'last_activity_at' => now(),
        ]);

        // Bust page-1 message cache so next render shows the new message
        Cache::forget("sanco:conv:{$data['conversation_id']}:latest_messages");

        // Bust inbox for all participants so the sidebar re-sorts
        $convo = Conversation::find($data['conversation_id']);
        if ($convo) {
            foreach ($convo->participant_ids as $pid) {
                Cache::forget("sanco:user:{$pid}:inbox");
            }
            Cache::forget("sanco:conv:{$data['conversation_id']}:details");
        }

        return $message;
    }

    public function markRead(string $messageId, string $userId): void
    {
        $message = Message::find($messageId);
        if ($message) {
            $message->markReadBy($userId);
            // Invalidate cached page 1 for this conversation
            Cache::forget("sanco:conv:{$message->conversation_id}:latest_messages");
        }
    }

    public function addReaction(string $messageId, string $userId, string $emoji): void
    {
        $message = Message::find($messageId);
        if ($message) {
            $message->addReaction($userId, $emoji);
            Cache::forget("sanco:conv:{$message->conversation_id}:latest_messages");
        }
    }

    public function removeReaction(string $messageId, string $userId): void
    {
        $message = Message::find($messageId);
        if ($message) {
            $message->removeReaction($userId);
            Cache::forget("sanco:conv:{$message->conversation_id}:latest_messages");
        }
    }
}
