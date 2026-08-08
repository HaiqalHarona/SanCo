<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    protected ConversationService $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Get inbox for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $inbox = $this->conversationService->getInbox($request->user());
        
        $formatted = $inbox->map(function ($convo) {
            return [
                'id' => $convo->_id,
                'type' => $convo->type, // 'direct' or 'group'
                'name' => $convo->display_data['name'] ?? $convo->name,
                'avatar' => $convo->display_data['avatar'] ?? $convo->avatar,
                'last_activity_at' => $convo->last_activity_at,
                'last_message' => $convo->lastMessage ? [
                    'id' => $convo->lastMessage->_id,
                    'body' => $convo->lastMessage->body,
                    'type' => $convo->lastMessage->type,
                    'sender_id' => $convo->lastMessage->sender_id,
                    'created_at' => $convo->lastMessage->created_at,
                ] : null,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Create a conversation (direct or group).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:direct,group',
            // Direct chat requirements
            'recipient_id' => 'required_if:type,direct|string',
            // Group chat requirements
            'name' => 'required_if:type,group|string|max:255',
            'participant_ids' => 'required_if:type,group|array',
            'participant_ids.*' => 'string',
        ]);

        $authId = $request->user()->_id;

        if ($request->input('type') === 'direct') {
            $recipientId = $request->input('recipient_id');
            if ($authId === $recipientId) {
                return response()->json(['error' => 'You cannot start a direct conversation with yourself.'], 400);
            }

            $convo = $this->conversationService->findOrCreateDirect($authId, $recipientId);
            return response()->json([
                'message' => 'Direct conversation resolved.',
                'conversation_id' => $convo->_id,
            ]);
        } else {
            $pids = $request->input('participant_ids');
            // Ensure creator is in participants list
            if (!in_array($authId, $pids)) {
                $pids[] = $authId;
            }

            $convo = $this->conversationService->createGroup(
                $authId,
                $request->input('name'),
                $pids
            );

            return response()->json([
                'message' => 'Group conversation created successfully.',
                'conversation_id' => $convo->_id,
            ], 201);
        }
    }

    /**
     * Show conversation details and keys for E2EE.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $convo = $this->conversationService->getConversation($id);
        if (!$convo) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Authorization check: Is user a participant?
        if (!in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404); // Hide existence (per safety rules)
        }

        $participants = $this->conversationService->getParticipants($id);
        $publicKeys = $this->conversationService->getParticipantKeys($id);

        return response()->json([
            'id' => $convo->_id,
            'type' => $convo->type,
            'name' => $convo->name,
            'avatar' => $convo->avatar,
            'participant_ids' => $convo->participant_ids,
            'participants' => $participants->map(function ($p) use ($publicKeys) {
                return [
                    'id' => $p->_id,
                    'name' => $p->name,
                    'avatar' => $p->avatar,
                    'public_key' => $publicKeys[(string) $p->_id] ?? null,
                ];
            }),
        ]);
    }

    /**
     * Add participant to a group conversation.
     */
    public function addParticipant(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
        ]);

        $convo = $this->conversationService->getConversation($id);
        if (!$convo) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Authorization check & check if group
        if ($convo->type !== 'group' || !in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $userId = $request->input('user_id');
        if (in_array($userId, $convo->participant_ids)) {
            return response()->json(['error' => 'User is already a participant.'], 400);
        }

        $this->conversationService->addParticipant($id, $userId);

        return response()->json([
            'message' => 'Participant added successfully.'
        ]);
    }

    /**
     * Remove participant from a group conversation.
     */
    public function removeParticipant(string $id, string $userId, Request $request): JsonResponse
    {
        $convo = $this->conversationService->getConversation($id);
        if (!$convo) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Authorization: must be group and requester must be a participant
        if ($convo->type !== 'group' || !in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Can't remove if they aren't in it
        if (!in_array($userId, $convo->participant_ids)) {
            return response()->json(['error' => 'User is not a participant.'], 400);
        }

        $this->conversationService->removeParticipant($id, $userId);

        return response()->json([
            'message' => 'Participant removed successfully.'
        ]);
    }
}
