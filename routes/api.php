<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Friendship;
use App\Events\MessageSent;
use App\Events\IncomingRequest;
use App\Events\LoadContactList;
use FurqanSiddiqui\BIP39\BIP39;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public Authentication Routes
Route::post('/login', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'user_tag' => 'required|string',
        'recovery_key' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $user = User::where('user_tag', $request->user_tag)->first();

    if (!$user || !Hash::check($request->recovery_key, $user->master_key)) {
        return response()->json(['message' => 'Invalid User Tag ID or Recovery Key.'], 401);
    }

    // Capture metadata for monitoring
    $ip = $request->ip();
    $browser = $request->header('User-Agent');
    $location = 'Unknown';

    try {
        $response = file_get_contents("http://ip-api.com/json/{$ip}?fields=status,message,country,city");
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $location = "{$data['city']}, {$data['country']}";
            }
        }
    } catch (\Exception $e) {
        // Fail silently
    }

    // Issue Sanctum Token
    $token = $user->createToken('api-token')->plainTextToken;

    // Update login status and location tracking
    $user->update([
        'current_session_id' => 'api_' . Str::random(20),
        'last_login_ip' => $ip,
        'last_login_browser' => $browser,
        'last_login_location' => $location,
    ]);

    return response()->json([
        'user' => $user,
        'token' => $token,
    ]);
});

Route::post('/register', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'avatar' => 'nullable|string', // base64 encoded image
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Generate E2E Recovery key (24 words mnemonic)
    $masterKey = implode(' ', BIP39::Generate(24)->words);

    // Unique user tag generation
    $userTag = 'SanCo_' . Str::lower(Str::random(10));
    while (User::where('user_tag', $userTag)->exists()) {
        $userTag = 'SanCo_' . Str::lower(Str::random(10));
    }

    $avatarUrl = null;
    if ($request->avatar) {
        if (preg_match('/^data:image\/(\w+);base64,/', $request->avatar, $type)) {
            $data = substr($request->avatar, strpos($request->avatar, ',') + 1);
            $type = strtolower($type[1]);
            if (in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                $data = base64_decode($data);
                if ($data !== false) {
                    if (!Storage::disk('public')->exists('avatars')) {
                        Storage::disk('public')->makeDirectory('avatars');
                    }
                    $filename = Str::random(40) . '.' . $type;
                    Storage::disk('public')->put('avatars/' . $filename, $data);
                    $avatarUrl = asset('storage/avatars/' . $filename);
                }
            }
        }
    }

    $user = User::create([
        'name' => $request->name,
        'avatar' => $avatarUrl ?? 'https://ui-avatars.com/api/?background=ec4899&color=fff&name=' . urlencode($request->name),
        'user_tag' => $userTag,
        'master_key' => bcrypt($masterKey),
    ]);

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'recovery_key' => $masterKey,
        'token' => $token,
    ], 201);
});

// Authenticated Routes (Protected by Laravel Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // User Profile Actions
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

    Route::post('/user/public-key', function (Request $request) {
        $request->validate(['public_key' => 'required|string']);
        $request->user()->update(['public_key' => $request->public_key]);
        return response()->json(['success' => true, 'public_key' => $request->public_key]);
    });

    // Contacts / Friends
    Route::get('/contacts', function (Request $request) {
        $auth_id = $request->user()->_id;
        $friendships = Friendship::where('status', 'accepted')
            ->where(function ($query) use ($auth_id) {
                $query->where('user_id', $auth_id)->orWhere('friend_id', $auth_id);
            })
            ->get();

        $friendsIds = $friendships->map(function ($f) use ($auth_id) {
            return (string) $f->user_id === (string) $auth_id ? (string) $f->friend_id : (string) $f->user_id;
        })->unique()->toArray();

        $contacts = User::whereIn('_id', $friendsIds)->get();
        return response()->json($contacts);
    });

    Route::post('/contacts/search', function (Request $request) {
        $request->validate(['user_tag' => 'required|string']);
        
        $user = User::where('user_tag', $request->user_tag)
            ->where('_id', '!=', $request->user()->_id)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'No user found with that tag.'], 404);
        }

        return response()->json($user);
    });

    Route::post('/contacts/request', function (Request $request) {
        $request->validate(['friend_id' => 'required|string']);
        
        $receiver = User::find($request->friend_id);
        if (!$receiver) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        try {
            $friendship = Friendship::sendRequest($request->user()->_id, $receiver->_id);
            broadcast(new IncomingRequest($receiver->_id, $request->user()->name))->toOthers();
            return response()->json(['success' => true, 'friendship' => $friendship]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    });

    Route::get('/contacts/pending', function (Request $request) {
        $userId = $request->user()->_id;
        $incoming = Friendship::getPendingRequests($userId);
        $sent = Friendship::getSentRequests($userId);
        
        return response()->json([
            'incoming' => $incoming,
            'sent' => $sent
        ]);
    });

    Route::post('/contacts/accept', function (Request $request) {
        $request->validate(['sender_id' => 'required|string']);
        
        try {
            Friendship::acceptRequest($request->user()->_id, $request->sender_id);
            broadcast(new LoadContactList($request->sender_id, $request->user()->_id))->toOthers();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    });

    Route::post('/contacts/reject', function (Request $request) {
        $request->validate(['sender_id' => 'required|string']);
        
        try {
            Friendship::rejectRequest($request->user()->_id, $request->sender_id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    });

    // Conversations / Inbox
    Route::get('/conversations', function (Request $request) {
        $inbox = Conversation::getInboxFor($request->user());
        return response()->json($inbox);
    });

    Route::post('/conversations', function (Request $request) {
        $request->validate(['participant_id' => 'required|string']);
        
        try {
            $convo = Conversation::findOrCreateDirect($request->user()->_id, $request->participant_id);
            return response()->json($convo);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    });

    Route::get('/conversations/{id}/messages', function (Request $request, $id) {
        $convo = Conversation::find($id);
        if (!$convo || !$convo->hasParticipant($request->user()->_id)) {
            return response()->json(['message' => 'Conversation not found or unauthorized.'], 403);
        }

        $limit = $request->query('limit', 20);
        $messages = Message::getMessages($id, $limit);
        return response()->json($messages);
    });

    Route::post('/conversations/{id}/messages', function (Request $request, $id) {
        $convo = Conversation::find($id);
        if (!$convo || !$convo->hasParticipant($request->user()->_id)) {
            return response()->json(['message' => 'Conversation not found or unauthorized.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
            'type' => 'nullable|string|in:text,image,file,audio,video',
            'nonce' => 'nullable|string',
            'enc_keys' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $isEncrypted = !empty($request->enc_keys);

        $message = Message::sendMessage([
            'conversation_id' => $id,
            'sender_id' => $request->user()->_id,
            'body' => $request->body,
            'type' => $request->type ?? 'text',
            'metadata' => [
                'nonce' => $request->nonce,
                'enc_keys' => $request->enc_keys,
                'is_encrypted' => $isEncrypted
            ]
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message, 201);
    });

    // Token Revocation
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Token revoked successfully.']);
    });
});
