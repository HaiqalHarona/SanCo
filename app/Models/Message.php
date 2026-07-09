<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'messages';

    protected static function booted()
    {
        static::saving(function (Message $message) {
            if ($message->type !== 'system') {
                $metadata = $message->metadata ?? [];
                $isEncrypted = $metadata['is_encrypted'] ?? false;
                $nonce = $metadata['nonce'] ?? null;
                $encKeys = $metadata['enc_keys'] ?? null;

                if (!$isEncrypted || empty($nonce) || empty($encKeys)) {
                    throw new \InvalidArgumentException('Message body must be end-to-end encrypted (E2EE). Plaintext is rejected.');
                }
            }
        });
    }

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',           // text | image | file | audio | video | system
        'body',           // text content
        'read_by',        // array of { user_id, read_at }
        'reactions',      // array of { user_id, emoji }
        'reply_to_id',    // parent Message ObjectId (thread)
        'is_edited',
        'edited_at',
        'metadata',
    ];

    protected $casts = [
        'read_by'     => 'array',
        'reactions'   => 'array',
        'is_edited'   => 'boolean',
        'edited_at'   => 'datetime',
        'metadata'    => 'array',
    ];

    protected $with = ['sender'];

    // ── Relationships ──────────────────────────────────────────

    /**
     * The conversation this message belongs to.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The user who sent this message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The message this is a reply to (thread parent).
     */
    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * All replies to this message (thread children).
     */
    public function replies()
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    /**
     * Attachments embedded within this message.
     */
    public function attachments()
    {
        return $this->embedsMany(Attachment::class);
    }

    // ── Helpers (Instance) ─────────────────────────────────────

    /**
     * Mark this message as read by a user.
     */
    public function markReadBy(string $userId): void
    {
        $alreadyRead = collect($this->read_by)
            ->pluck('user_id')
            ->contains($userId);

        if (! $alreadyRead) {
            $this->push('read_by', [
                'user_id' => $userId,
                'read_at' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Check if this message has been read by a user.
     */
    public function isReadBy(string $userId): bool
    {
        return collect($this->read_by)
            ->pluck('user_id')
            ->contains($userId);
    }

    /**
     * Add or replace a reaction from a user.
     */
    public function addReaction(string $userId, string $emoji): void
    {
        // Remove existing reaction from same user first
        $this->pull('reactions', ['user_id' => $userId]);

        $this->push('reactions', [
            'user_id' => $userId,
            'emoji'   => $emoji,
        ]);
    }

    /**
     * Remove a reaction from a user.
     */
    public function removeReaction(string $userId): void
    {
        $this->pull('reactions', ['user_id' => $userId]);
    }

    /**
     * Check if the message was sent by the currently authenticated user.
     */
    public function isSentByMe(): bool
    {
        return (string) $this->sender_id === (string) auth()->id();
    }

    /**
     * Check if the message was sent by a specific user.
     */
    public function isSentBy(string $userId): bool
    {
        return (string) $this->sender_id === (string) $userId;
    }

    // ── Static Helpers ─────────────────────────────────────────

    /**
     * Optimized helper to create and send a message.
     * This method handles the creation of the message and automatically updates 
     * the parent conversation's last activity and message reference.
     */
    public static function sendMessage(array $data): self
    {
        $message = static::create([
            'conversation_id' => $data['conversation_id'],
            'sender_id'       => $data['sender_id'],
            'type'            => $data['type'] ?? 'text',
            'body'            => $data['body'] ?? '',
            'read_by'         => [
                [
                    'user_id' => $data['sender_id'],
                    'read_at' => now()->toISOString()
                ]
            ],
            'reply_to_id'     => $data['reply_to_id'] ?? null,
            'metadata'        => $data['metadata'] ?? [],
        ]);

        // This ensures the inbox list loads instantly without complex joins or subqueries.
        Conversation::where('_id', $data['conversation_id'])->update([
            'last_message_id'  => $message->_id,
            'last_activity_at' => now(),
        ]);

        return $message;
    }

    /**
     * Load messages in the conversation with pagination.
     */
    public static function getMessages(string $conversationId, int $loadLimit = 20)
    {
        return static::where('conversation_id', $conversationId)
            ->with('attachments')
            ->latest()
            ->paginate($loadLimit);
    }
}
