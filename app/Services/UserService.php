<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use FurqanSiddiqui\BIP39\BIP39;

class UserService
{
    public function getProfile(string $userId): ?User
    {
        return Cache::remember("sanco:user:{$userId}:profile", now()->addHours(24), function () use ($userId) {
            return User::find($userId);
        });
    }

    public function getPublicKey(string $userId): ?string
    {
        $cacheKey = "sanco:user:{$userId}:public_key";
        $val = Cache::get($cacheKey);
        if ($val !== null) {
            return $val;
        }

        $val = User::where('_id', $userId)->value('public_key');
        if ($val !== null) {
            Cache::forever($cacheKey, $val);
        }
        return $val;
    }

    public function updateProfile(string $userId, string $name): User
    {
        $user = User::find($userId);
        $user->name = $name;
        $user->save();

        Cache::forget("sanco:user:{$userId}:profile");

        return $user;
    }

    public function updateAvatar(string $userId, string $base64Image): string
    {
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            throw new \Exception('Invalid image format');
        }

        $data = substr($base64Image, strpos($base64Image, ',') + 1);
        $type = strtolower($type[1]);

        if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
            throw new \Exception('Invalid image type');
        }

        $data = base64_decode($data);
        if ($data === false) {
            throw new \Exception('base64_decode failed');
        }

        if (!Storage::disk('public')->exists('avatars')) {
            Storage::disk('public')->makeDirectory('avatars');
        }

        $filename = Str::random(40) . '.' . $type;
        Storage::disk('public')->put('avatars/' . $filename, $data);

        $url = asset('storage/avatars/' . $filename);

        User::where('_id', $userId)->update(['avatar' => $url]);

        Cache::forget("sanco:user:{$userId}:profile");

        return $url;
    }

    public function syncPublicKey(string $userId, string $publicKey): void
    {
        User::where('_id', $userId)->update(['public_key' => $publicKey]);

        Cache::forget("sanco:user:{$userId}:public_key");
        Cache::forget("sanco:user:{$userId}:profile");

        // Bust all conversation participant key caches that this user is part of
        $convIds = \App\Models\Conversation::where('participant_ids', $userId)->pluck('_id');
        foreach ($convIds as $convId) {
            Cache::forget("sanco:conv:{$convId}:public_keys");
            Cache::forget("sanco:conv:{$convId}:details");
        }
    }

    public function rotateKey(string $userId): string
    {
        $masterKey = implode(' ', BIP39::Generate(24)->words);

        User::where('_id', $userId)->update(['master_key' => bcrypt($masterKey)]);

        Cache::forget("sanco:user:{$userId}:profile");
        Cache::forget("sanco:user:{$userId}:public_key");

        // Bust all conversation participant key caches that this user is part of
        $convIds = \App\Models\Conversation::where('participant_ids', $userId)->pluck('_id');
        foreach ($convIds as $convId) {
            Cache::forget("sanco:conv:{$convId}:public_keys");
            Cache::forget("sanco:conv:{$convId}:details");
        }

        return $masterKey;
    }

    public function setSession(string $userId, string $sessionId): void
    {
        Cache::put("sanco:user:{$userId}:session", $sessionId, now()->addHours(2));
    }

    public function getSession(string $userId): ?string
    {
        return Cache::get("sanco:user:{$userId}:session");
    }

    public function forgetSession(string $userId): void
    {
        Cache::forget("sanco:user:{$userId}:session");
    }
}
