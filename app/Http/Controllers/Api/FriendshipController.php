<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FriendshipService;
use App\Events\IncomingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    protected FriendshipService $friendshipService;

    public function __construct(FriendshipService $friendshipService)
    {
        $this->friendshipService = $friendshipService;
    }

    /**
     * Get accepted friends list.
     */
    public function index(Request $request): JsonResponse
    {
        $friends = $this->friendshipService->getFriends($request->user()->_id);
        
        $formatted = $friends->map(function ($friend) {
            return [
                'id' => $friend->_id,
                'name' => $friend->name,
                'email' => $friend->email,
                'avatar' => $friend->avatar,
                'status' => $friend->status,
                'public_key' => $friend->public_key,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Get pending incoming friend requests.
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $pending = $this->friendshipService->getPendingRequests($request->user()->_id);
        
        $formatted = $pending->map(function ($req) {
            return [
                'id' => $req->_id,
                'sender' => $req->user ? [
                    'id' => $req->user->_id,
                    'name' => $req->user->name,
                    'avatar' => $req->user->avatar,
                ] : null,
                'created_at' => $req->created_at,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Send a friend request.
     */
    public function sendRequest(Request $request): JsonResponse
    {
        $request->validate([
            'friend_id' => 'required|string',
        ]);

        $senderId = $request->user()->_id;
        $receiverId = $request->input('friend_id');

        if ($senderId === $receiverId) {
            return response()->json(['error' => 'You cannot send a friend request to yourself.'], 400);
        }

        try {
            $friendship = $this->friendshipService->sendRequest($senderId, $receiverId);
            
            // Broadcast incoming request event to the receiver
            broadcast(new IncomingRequest($receiverId, $request->user()->name))->toOthers();

            return response()->json([
                'message' => 'Friend request sent successfully.',
                'friendship_id' => $friendship->_id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Accept an incoming friend request.
     */
    public function acceptRequest(string $senderId, Request $request): JsonResponse
    {
        try {
            $this->friendshipService->acceptRequest($request->user()->_id, $senderId);
            return response()->json(['message' => 'Friend request accepted.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Reject an incoming friend request.
     */
    public function rejectRequest(string $senderId, Request $request): JsonResponse
    {
        try {
            $this->friendshipService->rejectRequest($request->user()->_id, $senderId);
            return response()->json(['message' => 'Friend request rejected.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Unfriend/delete mutual friendship.
     */
    public function unfriend(string $friendId, Request $request): JsonResponse
    {
        try {
            $this->friendshipService->unfriend($request->user()->_id, $friendId);
            return response()->json(['message' => 'Friend removed successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Block a user.
     */
    public function block(string $friendId, Request $request): JsonResponse
    {
        try {
            $this->friendshipService->blockUser($request->user()->_id, $friendId);
            return response()->json(['message' => 'User blocked.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Unblock a user.
     */
    public function unblock(string $friendId, Request $request): JsonResponse
    {
        try {
            $this->friendshipService->unblockUser($request->user()->_id, $friendId);
            return response()->json(['message' => 'User unblocked.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
