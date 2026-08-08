<?php

namespace App\Livewire\MessengerVolt;

use App\Services\ConversationService;
use App\Services\MessageService;
use App\Services\UserService;
use App\Events\MessageSent;
use Livewire\Attributes\Computed;

trait MessagingActions
{
    public $messageBody = '';

    public function selectConversation($id, $userId = null)
    {
        if (!$id && $userId) {
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
        if (!$this->selectedConversationId) {
            return null;
        }

        $convService = app(ConversationService::class);
        $convo = $convService->getConversation($this->selectedConversationId);

        $messages = app(MessageService::class)->getMessages($this->selectedConversationId, 1, $this->loadLimit);

        $convo->setRelation('messages', collect($messages->items())->reverse());
        $convo->participant_public_keys = $convService->getParticipantKeys($this->selectedConversationId);

        return $convo;
    }

    #[Computed]
    public function preloadChatList()
    {
        return app(ConversationService::class)->getInbox(auth()->user());
    }

    public function messageUser($encryptedBody = null, $nonce = null, $encryptedKeys = null)
    {
        if (!auth()->user()->master_key) {
            return;
        }
        if (!$this->selectedConversationId) {
            return;
        }

        $body = $encryptedBody ?? $this->messageBody;
        if (trim($body) === '') {
            return;
        }

        $message = app(MessageService::class)->send([
            'conversation_id' => $this->selectedConversationId,
            'sender_id' => auth()->id(),
            'body' => $body,
            'type' => 'text',
            'metadata' => [
                'nonce' => $nonce,
                'enc_keys' => $encryptedKeys,
                'is_encrypted' => !!$encryptedKeys,
            ],
        ]);

        $this->reset('messageBody');
        broadcast(new MessageSent($message))->toOthers();
        $this->dispatch('scroll-bottom');
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
        if (!$this->selectedConversationId) {
            return [];
        }

        return app(ConversationService::class)->getParticipantKeys($this->selectedConversationId);
    }
}
