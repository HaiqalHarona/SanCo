<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->_id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'public_key' => $user->public_key,
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'avatar_base64' => 'nullable|string',
        ]);

        $userId = $request->user()->_id;

        $user = $this->userService->updateProfile($userId, $request->input('name'));

        if ($request->filled('avatar_base64')) {
            try {
                $avatarUrl = $this->userService->updateAvatar($userId, $request->input('avatar_base64'));
                $user->avatar = $avatarUrl;
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->_id,
                'name' => $user->name,
                'avatar' => $user->avatar,
            ]
        ]);
    }

    /**
     * Sync E2EE public key.
     */
    public function syncPublicKey(Request $request): JsonResponse
    {
        $request->validate([
            'public_key' => 'required|string',
        ]);

        $userId = $request->user()->_id;
        $this->userService->syncPublicKey($userId, $request->input('public_key'));

        return response()->json([
            'message' => 'Public key synced successfully.',
            'public_key' => $request->input('public_key'),
        ]);
    }
}
