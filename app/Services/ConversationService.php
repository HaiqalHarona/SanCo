<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConversationService
{
    // ── Reads (all cached) ─────────────────────────────────────

    public function getInbox(User $user): Collection
    {
        $userId = (string) $user->_id;

        return Cache::remember("sanco:user:{$userId}:inbox", now()->addHour(), function () use ($user) {
            $conversations = Conversation::where('participant_ids', $user->_id)
                ->with(['lastMessage'])
                ->latest('last_activity_at')
                ->get();

            $allParticipantIds = $conversations->pluck('participant_ids')
                ->flatten()
                ->unique()
                ->reject(fn($id) => (string) $id === (string) $user->_id);

            $users = User::whereIn('_id', $allParticipantIds)->get()->keyBy('_id');

            return $conversations->map(function (Conversation $convo) use ($users) {
                $convo->display_data = $convo->getDisplayInfo($users);
                return $convo;
            });
        });
    }

    public function getConversation(string $convId): ?Conversation
    {
        return Cache::remember("sanco:conv:{$convId}:details", now()->addHour(), function () use ($convId) {
            return Conversation::find($convId);
        });
    }

    public function getParticipantKeys(string $convId): array
    {
        $cacheKey = "sanco:conv:{$convId}:public_keys";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            // Check if there are any null values in the cached array
            if (!in_array(null, $cached, true)) {
                return $cached;
            }
        }

        $convo = Conversation::find($convId);
        if (!$convo) {
            return [];
        }

        $participants = User::whereIn('_id', $convo->participant_ids)
            ->get(['_id', 'public_key']);

        $keys = $participants->mapWithKeys(function ($user) {
            return [(string) $user->_id => $user->public_key];
        })->toArray();

        // Only cache if there are no null keys (i.e. all participants have keys set)
        if (!in_array(null, $keys, true) && !empty($keys)) {
            Cache::put($cacheKey, $keys, now()->addMinutes(30));
        }

        return $keys;
    }

    public function getParticipants(string $convId): Collection
    {
        return Cache::remember("sanco:conv:{$convId}:participants", now()->addHour(), function () use ($convId) {
            $convo = Conversation::find($convId);
            if (!$convo) {
                return collect();
            }
            return User::whereIn('_id', $convo->participant_ids)->get();
        });
    }

    // ── Writes (each invalidates inbox of all participants) ─────

    public function findOrCreateDirect(string $userA, string $userB): Conversation
    {
        $convo = Conversation::findOrCreateDirect($userA, $userB);

        // Only bust if newly created
        if ($convo->wasRecentlyCreated) {
            Cache::forget("sanco:user:{$userA}:inbox");
            Cache::forget("sanco:user:{$userB}:inbox");
        }

        return $convo;
    }

    public function createGroup(string $creatorId, string $name, array $participantIds, ?string $avatar = null): Conversation
    {
        $convo = Conversation::create([
            'type'             => 'group',
            'name'             => $name,
            'avatar'           => $avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=fff',
            'participant_ids'  => $participantIds,
            'last_activity_at' => now(),
            'created_by'       => $creatorId,
        ]);

        foreach ($participantIds as $pid) {
            Cache::forget("sanco:user:{$pid}:inbox");
        }

        return $convo;
    }

    public function addParticipant(string $convId, string $userId): void
    {
        $convo = Conversation::findOrFail($convId);
        $convo->addParticipant($userId);

        Cache::forget("sanco:conv:{$convId}:participants");
        Cache::forget("sanco:conv:{$convId}:public_keys");
        Cache::forget("sanco:conv:{$convId}:details");
        Cache::forget("sanco:user:{$userId}:inbox");
    }

    public function removeParticipant(string $convId, string $userId): void
    {
        $convo = Conversation::findOrFail($convId);
        $convo->removeParticipant($userId);

        Cache::forget("sanco:conv:{$convId}:participants");
        Cache::forget("sanco:conv:{$convId}:public_keys");
        Cache::forget("sanco:conv:{$convId}:details");
        Cache::forget("sanco:user:{$userId}:inbox");
    }

    public function bustInboxForParticipants(string $convId): void
    {
        $convo = Conversation::find($convId);
        if (!$convo) {
            return;
        }

        foreach ($convo->participant_ids as $pid) {
            Cache::forget("sanco:user:{$pid}:inbox");
        }

        Cache::forget("sanco:conv:{$convId}:details");
    }

    public function bustParticipantKeys(string $convId): void
    {
        Cache::forget("sanco:conv:{$convId}:public_keys");
    }
}
