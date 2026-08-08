# SanCo Secure API Documentation

SanCo exposes a secure JSON API protected by **Laravel Sanctum**. Personal Access Tokens (PATs) are issued upon successful authentication and must be included in the `Authorization` header as a bearer token for all protected endpoints.

---

## 1. Authentication & Token Management

### Register User
* **Endpoint**: `POST /api/register`
* **Authentication**: Public
* **Request Body**:
  ```json
  {
    "name": "John Doe",
    "avatar": "data:image/png;base64,iVBORw0KG..." // Optional base64 encoded image
  }
  ```
* **Success Response (201 Created)**:
  ```json
  {
    "user": {
      "id": "6a3831307509a4147b0e1be2",
      "name": "John Doe",
      "avatar": "http://localhost/storage/avatars/random.png",
      "user_tag": "SanCo_kmq6nkzpov"
    },
    "recovery_key": "word1 word2 ... word24", // 24-word E2E recovery mnemonic
    "token": "1|abcdef123456..." // Sanctum Personal Access Token
  }
  ```

### Login / Issue Token
* **Endpoint**: `POST /api/login`
* **Authentication**: Public
* **Request Body**:
  ```json
  {
    "user_tag": "SanCo_kmq6nkzpov",
    "recovery_key": "word1 word2 ... word24"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "user": {
      "id": "6a3831307509a4147b0e1be2",
      "name": "John Doe",
      "avatar": "http://localhost/storage/avatars/random.png",
      "user_tag": "SanCo_kmq6nkzpov",
      "public_key": "v6Qw9IGD7UhKoUFeQxELXKRVdGcn3nKfMLX97gHWAgY"
    },
    "token": "2|ghijk7891011..."
  }
  ```

### Logout / Revoke Token
* **Endpoint**: `POST /api/logout`
* **Authentication**: Sanctum Bearer Token
* **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Token revoked successfully."
  }
  ```

---

## 2. User Settings & Cryptography

### Get Current User Profile
* **Endpoint**: `GET /api/user`
* **Authentication**: Sanctum Bearer Token
* **Success Response (200 OK)**:
  ```json
  {
    "id": "6a3831307509a4147b0e1be2",
    "name": "John Doe",
    "email": "johndoe@example.com",
    "avatar": "http://localhost/storage/avatars/random.png",
    "user_tag": "SanCo_kmq6nkzpov",
    "public_key": "v6Qw9IGD7UhKoUFeQxELXKRVdGcn3nKfMLX97gHWAgY"
  }
  ```

### Update Public Key
Updates the E2E public key on the server. Essential when registering keys or logging in from a new device.
* **Endpoint**: `POST /api/user/public-key`
* **Authentication**: Sanctum Bearer Token
* **Request Body**:
  ```json
  {
    "public_key": "v6Qw9IGD7UhKoUFeQxELXKRVdGcn3nKfMLX97gHWAgY"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "public_key": "v6Qw9IGD7UhKoUFeQxELXKRVdGcn3nKfMLX97gHWAgY"
  }
  ```

---

## 3. Contacts & Friendships

### Get Contacts List
Retrieves all accepted contacts/friends.
* **Endpoint**: `GET /api/contacts`
* **Authentication**: Sanctum Bearer Token
* **Success Response (200 OK)**:
  ```json
  [
    {
      "id": "6a359b092fdf449f2000bb22",
      "name": "Johani Mahamd",
      "avatar": "http://localhost/storage/avatars/other.png",
      "user_tag": "SanCo_i7kxvk0u87",
      "public_key": "hGyS-L5_3ClLy9zIhbLDdAWt8FgL3UAU29tiuDPOvXQ"
    }
  ]
  ```

### Search User by Tag
* **Endpoint**: `POST /api/contacts/search`
* **Authentication**: Sanctum Bearer Token
* **Request Body**:
  ```json
  {
    "user_tag": "SanCo_i7kxvk0u87"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "id": "6a359b092fdf449f2000bb22",
    "name": "Johani Mahamd",
    "avatar": "http://localhost/storage/avatars/other.png",
    "user_tag": "SanCo_i7kxvk0u87",
    "public_key": "hGyS-L5_3ClLy9zIhbLDdAWt8FgL3UAU29tiuDPOvXQ"
  }
  ```

### Send Friend Request
* **Endpoint**: `POST /api/contacts/request`
* **Authentication**: Sanctum Bearer Token
* **Request Body**:
  ```json
  {
    "friend_id": "6a359b092fdf449f2000bb22"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "friendship": {
      "id": "6a3838017509a4147b0e1bed",
      "user_id": "6a3831307509a4147b0e1be2",
      "friend_id": "6a359b092fdf449f2000bb22",
      "status": "pending",
      "action_user_id": "6a3831307509a4147b0e1be2"
    }
  }
  ```

### Get Pending Friend Requests
Retrieves list of incoming and outgoing pending friend requests.
* **Endpoint**: `GET /api/contacts/pending`
* **Authentication**: Sanctum Bearer Token
* **Success Response (200 OK)**:
  ```json
  {
    "incoming": [
      {
        "id": "6a3838017509a4147b0e1bed",
        "user_id": "6a359b092fdf449f2000bb22",
        "friend_id": "6a3831307509a4147b0e1be2",
        "status": "pending",
        "user": {
          "id": "6a359b092fdf449f2000bb22",
          "name": "Johani Mahamd",
          "avatar": "http://localhost/storage/avatars/other.png"
        }
      }
    ],
    "sent": []
  }
  ```

### Accept Friend Request
* **Endpoint**: `POST /api/contacts/accept`
* **Authentication**: Sanctum Bearer Token
* **Request Body**:
  ```json
  {
    "sender_id": "6a359b092fdf449f2000bb22"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "success": true
  }
  ```

### Reject Friend Request
* **Endpoint**: `POST /api/contacts/reject`
* **Authentication**: Sanctum Bearer Token
* **Request Body**:
  ```json
  {
    "sender_id": "6a359b092fdf449f2000bb22"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "success": true
  }
  ```

---

## 4. Conversations & Messaging

### List Conversations / Inbox
Retrieves list of active chats for the current user.
* **Endpoint**: `GET /api/conversations`
* **Authentication**: Sanctum Bearer Token
* **Success Response (200 OK)**:
  ```json
  [
    {
      "id": "6a3837c87509a4147b0e1be6",
      "type": "direct",
      "participant_ids": [
        "6a359b092fdf449f2000bb22",
        "6a3831307509a4147b0e1be2"
      ],
      "last_activity_at": "2026-06-21T19:14:24.505Z",
      "last_message_id": "6a3838107509a4147b0e1bea",
      "display_data": {
        "id": "6a359b092fdf449f2000bb22",
        "name": "Johani Mahamd",
        "avatar": "http://localhost/storage/avatars/other.png",
        "status": "online"
      }
    }
  ]
  ```

### Create / Find Direct Conversation
* **Endpoint**: `POST /api/conversations`
* **Authentication**: Sanctum Bearer Token
* **Request Body**:
  ```json
  {
    "participant_id": "6a359b092fdf449f2000bb22"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "id": "6a3837c87509a4147b0e1be6",
    "type": "direct",
    "participant_ids": [
      "6a359b092fdf449f2000bb22",
      "6a3831307509a4147b0e1be2"
    ],
    "created_by": "6a3831307509a4147b0e1be2",
    "last_activity_at": "2026-06-22T04:00:00.000Z"
  }
  ```

### Get Conversation Messages
Loads messages in a specific conversation with pagination.
* **Endpoint**: `GET /api/conversations/{id}/messages`
* **Authentication**: Sanctum Bearer Token
* **Query Parameters**:
  * `limit`: Page load limit (default: 20)
  * `page`: Pagination page number
* **Success Response (200 OK)**:
  ```json
  {
    "current_page": 1,
    "data": [
      {
        "id": "6a3838107509a4147b0e1bea",
        "conversation_id": "6a3837c87509a4147b0e1be6",
        "sender_id": "6a3831307509a4147b0e1be2",
        "type": "text",
        "body": "1xwstBXJ5w6xU0EI90I1R_d1zw", // Base64 encrypted body
        "metadata": {
          "nonce": "ivwTlZeDg2JBtqbQVcklYFqsvWtf0EyD",
          "enc_keys": {
            "6a359b092fdf449f2000bb22": "wgjX7ppujOW...",
            "6a3831307509a4147b0e1be2": "yzKbMbtkpsx..."
          },
          "is_encrypted": true
        },
        "sender": {
          "id": "6a3831307509a4147b0e1be2",
          "name": "Nino Nakano",
          "avatar": "https://avatars.githubusercontent.com/u/194076583?v=4"
        }
      }
    ],
    "next_page_url": null,
    "prev_page_url": null,
    "to": 1,
    "total": 1
  }
  ```

### Send Message
Sends a new message into a conversation (supports E2EE data format).
* **Endpoint**: `POST /api/conversations/{id}/messages`
* **Authentication**: Sanctum Bearer Token
* **Request Body**:
  ```json
  {
    "body": "1xwstBXJ5w6xU0EI90I1R_d1zw", // Plaintext or Base64 Ciphertext
    "type": "text", // text | image | file | audio | video
    "nonce": "ivwTlZeDg2JBtqbQVcklYFqsvWtf0EyD", // Optional E2EE nonce
    "enc_keys": { // Optional E2EE key envelope map
      "6a359b092fdf449f2000bb22": "wgjX7ppujOW...",
      "6a3831307509a4147b0e1be2": "yzKbMbtkpsx..."
    }
  }
  ```
* **Success Response (210 Created)**:
  ```json
  {
    "id": "6a3838107509a4147b0e1bea",
    "conversation_id": "6a3837c87509a4147b0e1be6",
    "sender_id": "6a3831307509a4147b0e1be2",
    "type": "text",
    "body": "1xwstBXJ5w6xU0EI90I1R_d1zw",
    "metadata": {
      "nonce": "ivwTlZeDg2JBtqbQVcklYFqsvWtf0EyD",
      "enc_keys": {
        "6a359b092fdf449f2000bb22": "wgjX7ppujOW...",
        "6a3831307509a4147b0e1be2": "yzKbMbtkpsx..."
      },
      "is_encrypted": true
    }
  }
  ```
