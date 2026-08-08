<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    protected MessageService $messageService;
    protected ConversationService $conversationService;

    public function __construct(MessageService $messageService, ConversationService $conversationService)
    {
        $this->messageService = $messageService;
        $this->conversationService = $conversationService;
    }

    /**
     * Get paginated messages in a conversation.
     */
    public function index(string $conversationId, Request $request): JsonResponse
    {
        $convo = $this->conversationService->getConversation($conversationId);
        if (!$convo || !in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $paginator = $this->messageService->getMessages($conversationId, $page, $limit);

        return response()->json([
            'current_page' => $paginator->currentPage(),
            'data' => collect($paginator->items())->map(function (Message $msg) {
                return [
                    'id' => $msg->_id,
                    'conversation_id' => $msg->conversation_id,
                    'sender_id' => $msg->sender_id,
                    'body' => $msg->body,
                    'type' => $msg->type,
                    'read_by' => $msg->read_by,
                    'reply_to_id' => $msg->reply_to_id,
                    'metadata' => $msg->metadata,
                    'reactions' => $msg->reactions,
                    'created_at' => $msg->created_at,
                ];
            }),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * Send a new encrypted message.
     */
    public function store(string $conversationId, Request $request): JsonResponse
    {
        $convo = $this->conversationService->getConversation($conversationId);
        if (!$convo || !in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $request->validate([
            'body' => 'required|string',
            'type' => 'nullable|string|in:text,image,file',
            'reply_to_id' => 'nullable|string',
            'metadata' => 'required|array',
            'metadata.is_encrypted' => 'required|accepted', // Must be E2EE
            'metadata.nonce' => 'required|string',
            'metadata.enc_keys' => 'required|array',
        ]);

        try {
            $message = $this->messageService->send([
                'conversation_id' => $conversationId,
                'sender_id' => $request->user()->_id,
                'body' => $request->input('body'),
                'type' => $request->input('type', 'text'),
                'reply_to_id' => $request->input('reply_to_id'),
                'metadata' => $request->input('metadata'),
            ]);

            return response()->json([
                'message' => 'Message sent.',
                'data' => [
                    'id' => $message->_id,
                    'body' => $message->body,
                    'type' => $message->type,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at,
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark a message as read.
     */
    public function read(string $id, Request $request): JsonResponse
    {
        $message = Message::find($id);
        if (!$message) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $convo = $this->conversationService->getConversation($message->conversation_id);
        if (!$convo || !in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->messageService->markRead($id, $request->user()->_id);

        return response()->json(['message' => 'Message marked as read.']);
    }

    /**
     * Add a reaction to a message.
     */
    public function react(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        $message = Message::find($id);
        if (!$message) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $convo = $this->conversationService->getConversation($message->conversation_id);
        if (!$convo || !in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->messageService->addReaction($id, $request->user()->_id, $request->input('emoji'));

        return response()->json(['message' => 'Reaction added.']);
    }

    /**
     * Remove a reaction from a message.
     */
    public function unreact(string $id, Request $request): JsonResponse
    {
        $message = Message::find($id);
        if (!$message) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $convo = $this->conversationService->getConversation($message->conversation_id);
        if (!$convo || !in_array($request->user()->_id, $convo->participant_ids)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->messageService->removeReaction($id, $request->user()->_id);

        return response()->json(['message' => 'Reaction removed.']);
    }
}
