<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\FriendshipController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    
    // AUTH & USER PROFILE

    // Retrieves details of the currently authenticated user (ID, name, email, avatar, E2EE public key)
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');

    // Updates authenticated user's profile information (name and base64 encoded avatar image)
    Route::put('/user/profile', [AuthController::class, 'updateProfile'])->name('api.user.profile');

    // Registers/updates the user's end-to-end encryption (E2EE) RSA/ECC public key in database
    Route::post('/user/keys/sync', [AuthController::class, 'syncPublicKey'])->name('api.user.keys.sync');


    // CONVERSATIONS & CHANNELS

    // Gets lists of active direct or group conversations for the user, with display names and last message summary
    Route::get('/conversations', [ConversationController::class, 'index'])->name('api.conversations.index');

    // Resolves a direct conversation with another user or creates a new group chat
    Route::post('/conversations', [ConversationController::class, 'store'])->name('api.conversations.store');

    // Fetches detailed metadata of a specific conversation, including participant profiles and E2EE keys
    Route::get('/conversations/{id}', [ConversationController::class, 'show'])->name('api.conversations.show');

    // Adds a participant to an existing group conversation (restricted to current conversation members only)
    Route::post('/conversations/{id}/participants', [ConversationController::class, 'addParticipant'])->name('api.conversations.participants.add');

    // Removes a participant from a group conversation (restricted to current conversation members only)
    Route::delete('/conversations/{id}/participants/{userId}', [ConversationController::class, 'removeParticipant'])->name('api.conversations.participants.remove');


    // MESSAGES & CHAT ACTIONS

    // Retrieves paginated list of E2EE encrypted messages in a conversation (supports ?page and ?limit query params)
    Route::get('/conversations/{id}/messages', [MessageController::class, 'index'])->name('api.messages.index');

    // Stores and sends a new message to a conversation. Enforces E2EE payload validation metadata structure
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store'])->name('api.messages.store');

    // Updates a message's read state by adding the current user's ID to the read receipts array
    Route::post('/messages/{id}/read', [MessageController::class, 'read'])->name('api.messages.read');

    // Adds an emoji reaction to a specific message
    Route::post('/messages/{id}/reactions', [MessageController::class, 'react'])->name('api.messages.react');

    // Removes the authenticated user's reaction from a message
    Route::delete('/messages/{id}/reactions', [MessageController::class, 'unreact'])->name('api.messages.unreact');


    // FRIENDSHIPS & RELATIONSHIPS

    // Retrieves the authenticated user's current accepted friends list
    Route::get('/friends', [FriendshipController::class, 'index'])->name('api.friends.index');

    // Retrieves incoming pending friend requests waiting for approval
    Route::get('/friends/requests/pending', [FriendshipController::class, 'pendingRequests'])->name('api.friends.requests.pending');

    // Sends a new pending friend request to another user
    Route::post('/friends/requests', [FriendshipController::class, 'sendRequest'])->name('api.friends.requests.send');

    // Accepts a pending incoming friend request, establishing a reciprocal friendship link
    Route::put('/friends/requests/{senderId}/accept', [FriendshipController::class, 'acceptRequest'])->name('api.friends.requests.accept');

    // Rejects/deletes an incoming pending friend request
    Route::delete('/friends/requests/{senderId}/reject', [FriendshipController::class, 'rejectRequest'])->name('api.friends.requests.reject');

    // Removes an accepted friend relationship, deleting both reciprocal records
    Route::delete('/friends/{friendId}', [FriendshipController::class, 'unfriend'])->name('api.friends.unfriend');

    // Blocks a user (blocks incoming requests, deletes active friendships, and stops direct communication)
    Route::post('/friends/{friendId}/block', [FriendshipController::class, 'block'])->name('api.friends.block');

    // Unblocks a user, restoring the ability to initiate contacts or send friend requests
    Route::delete('/friends/{friendId}/unblock', [FriendshipController::class, 'unblock'])->name('api.friends.unblock');
});

