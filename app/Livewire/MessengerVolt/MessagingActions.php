<?php

namespace App\Livewire\MessengerVolt;

use App\Events\MessageSent;
use App\Services\AttachmentStorageService;
use App\Services\ConversationService;
use App\Services\FriendshipService;
use App\Services\MessageService;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;

trait MessagingActions
{
    public $messageBody = '';

    public function selectConversation($id, $userId = null)
    {
        if (! $id && $userId) {
            $convo = app(ConversationService::class)->findOrCreateDirect(auth()->id(), $userId);
            $this->selectedConversationId = (string) $convo->_id;
        } else {
            $this->selectedConversationId = (string) $id;
        }

        $this->dispatch('scroll-bottom');
    }

    #[Computed]
    public function selectedConversation()
    {
        if (! $this->selectedConversationId) {
            return null;
        }

        $convService = app(ConversationService::class);
        $convo = $convService->getConversation($this->selectedConversationId);

        $messages = app(MessageService::class)->getMessages($this->selectedConversationId, 1, $this->loadLimit);

        $convo->setRelation('messages', collect($messages->items())->reverse());
        $convo->participant_public_keys = $convService->getParticipantKeys($this->selectedConversationId);

        // Check blocking status for direct 1-on-1 conversations
        $convo->is_blocked_by_me = false;
        $convo->is_blocked_by_them = false;
        $convo->other_user_id = null;

        if (! empty($convo->participant_ids)) {
            $otherId = collect($convo->participant_ids)->first(fn ($id) => (string) $id !== (string) auth()->id());
            if ($otherId) {
                $friendshipService = app(FriendshipService::class);
                $convo->other_user_id = (string) $otherId;
                $convo->is_blocked_by_me = $friendshipService->isBlocked(auth()->id(), (string) $otherId);
                $convo->is_blocked_by_them = $friendshipService->isBlocked((string) $otherId, auth()->id());
            }
        }

        return $convo;
    }

    #[Computed]
    public function preloadChatList()
    {
        return app(ConversationService::class)->getInbox(auth()->user());
    }

    public function uploadEncryptedAttachment(array $attachmentPayload): array
    {
        $binary = base64_decode($attachmentPayload['enc_blob_base64']);
        $storageService = app(AttachmentStorageService::class);
        $stored = $storageService->storeEncryptedBlob($binary, $attachmentPayload['mime_type']);

        return [
            'file_name' => $attachmentPayload['file_name'],
            'file_size' => $stored['file_size'],
            'mime_type' => $attachmentPayload['mime_type'],
            'url' => $stored['url'],
            'storage_path' => $stored['storage_path'],
            'width' => $attachmentPayload['width'] ?? null,
            'height' => $attachmentPayload['height'] ?? null,
            'duration' => $attachmentPayload['duration'] ?? null,
            'encryption_metadata' => [
                'is_encrypted' => true,
                'nonce' => $attachmentPayload['nonce'],
                'enc_keys' => $attachmentPayload['enc_keys'],
            ],
        ];
    }

    public function messageUser($encryptedBody = null, $nonce = null, $encryptedKeys = null, $attachments = [], $type = 'text')
    {
        if (! auth()->user()->master_key) {
            return;
        }
        if (! $this->selectedConversationId) {
            return;
        }

        $body = $encryptedBody ?? $this->messageBody;
        if (trim($body) === '' && empty($attachments)) {
            return;
        }

        // Auto-detect message type from first attachment if text
        if (! empty($attachments) && $type === 'text') {
            $firstMime = $attachments[0]['mime_type'] ?? '';
            if (str_starts_with($firstMime, 'image/')) {
                $type = 'image';
            } elseif (str_starts_with($firstMime, 'video/')) {
                $type = 'video';
            } elseif (str_starts_with($firstMime, 'audio/')) {
                $type = 'audio';
            } else {
                $type = 'file';
            }
        }

        try {
            $message = app(MessageService::class)->send([
                'conversation_id' => $this->selectedConversationId,
                'sender_id' => auth()->id(),
                'body' => $body,
                'type' => $type,
                'attachments' => $attachments,
                'metadata' => [
                    'nonce' => $nonce,
                    'enc_keys' => $encryptedKeys,
                    'is_encrypted' => (bool) $encryptedKeys,
                ],
            ]);

            $this->reset('messageBody');
            broadcast(new MessageSent($message))->toOthers();
            $this->dispatch('scroll-bottom');
        } catch (AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Error sending message: '.$e->getMessage());
        }
    }

    public function savePublicKey(string $publicKey)
    {
        app(UserService::class)->syncPublicKey(auth()->id(), $publicKey);

        if ($this->selectedConversationId) {
            app(ConversationService::class)->bustParticipantKeys($this->selectedConversationId);
        }

        unset($this->selectedConversation);
        $this->dispatch('$refresh');
    }

    public function getParticipantKeys(): array
    {
        if (! $this->selectedConversationId) {
            return [];
        }

        return app(ConversationService::class)->getParticipantKeys($this->selectedConversationId);
    }
}
