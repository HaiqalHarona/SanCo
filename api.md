# SanCo API Testing Reference (Thunder Client)

This reference contains all endpoints, methods, headers, and request body JSON structures to help you test the backend APIs using VS Code's **Thunder Client** or any REST client (Postman, Insomnia).

---

## Global Setup

### 1. Headers Preset
Ensure you add these headers to all protected requests:
*   **Accept**: `application/json`
*   **Content-Type**: `application/json`
*   **Authorization**: `Bearer {{your_personal_access_token}}`

### 2. Environment Variables
You can configure these variables in your Thunder Client environment:
*   `baseUrl`: `http://your-server-domain-or-ip/api` (or `http://localhost:8000/api` for local testing)

---

## 1. Authentication & User Profile

### GET /user (Get Profile)
*   **Method**: `GET`
*   **URL**: `{{baseUrl}}/user`
*   **Headers**: Authorization Bearer

---

### PUT /user/profile (Update Profile)
*   **Method**: `PUT`
*   **URL**: `{{baseUrl}}/user/profile`
*   **Body (JSON)**:
    ```json
    {
      "name": "John Doe",
      "avatar_base64": "data:image/png;base64,iVBORw0KGgoAAA..."
    }
    ```
    *(Note: `avatar_base64` is optional. Must be a valid base64 image string with correct prefix).*

---

### POST /user/keys/sync (Sync E2EE Public Key)
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/user/keys/sync`
*   **Body (JSON)**:
    ```json
    {
      "public_key": "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAv..."
    }
    ```

---

## 2. Conversations & Channels

### GET /conversations (Get Inbox)
Loads user conversations with the latest message and display meta-information.
*   **Method**: `GET`
*   **URL**: `{{baseUrl}}/conversations`

---

### POST /conversations (Create Direct or Group)
Creates a group chat or resolves a direct message conversation.
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/conversations`
*   **Body (JSON) — Direct Chat**:
    ```json
    {
      "type": "direct",
      "recipient_id": "64bfcb2a8b3d67f40a1b2c3d"
    }
    ```
*   **Body (JSON) — Group Chat**:
    ```json
    {
      "type": "group",
      "name": "Project Alpha Development",
      "participant_ids": [
        "64bfcb2a8b3d67f40a1b2c3d",
        "64bfce879c4a89e50b2c3d4e"
      ]
    }
    ```

---

### GET /conversations/{id} (Conversation details)
Returns details of a conversation, its participant lists, and their public keys for client-side E2EE encryption.
*   **Method**: `GET`
*   **URL**: `{{baseUrl}}/conversations/{{conversationId}}`

---

### POST /conversations/{id}/participants (Add Participant to Group)
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/conversations/{{conversationId}}/participants`
*   **Body (JSON)**:
    ```json
    {
      "user_id": "64bfce879c4a89e50b2c3d4e"
    }
    ```

---

### DELETE /conversations/{id}/participants/{userId} (Remove Participant)
*   **Method**: `DELETE`
*   **URL**: `{{baseUrl}}/conversations/{{conversationId}}/participants/{{userId}}`

---

## 3. Messages & E2EE Exchange

### GET /conversations/{id}/messages (Get Messages)
*   **Method**: `GET`
*   **URL**: `{{baseUrl}}/conversations/{{conversationId}}/messages?page=1&limit=20`

---

### POST /conversations/{id}/messages (Send Encrypted Message)
Sends a new message. **Plaintext messages are rejected** — E2EE structure in `metadata` is strictly required.
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/conversations/{{conversationId}}/messages`
*   **Body (JSON)**:
    ```json
    {
      "body": "U2VjcmV0IG1lc3NhZ2UgdGV4dA==", 
      "type": "text",
      "metadata": {
        "is_encrypted": true,
        "nonce": "d3d3d3d3d3d3d3d3d3d3d3d3d3d3d3d3",
        "enc_keys": {
          "64bfcb2a8b3d67f40a1b2c3d": "wrapped_symmetric_key_for_user_a",
          "64bfce879c4a89e50b2c3d4e": "wrapped_symmetric_key_for_user_b"
        }
      }
    }
    ```

---

### POST /messages/{id}/read (Mark as Read)
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/messages/{{messageId}}/read`

---

### POST /messages/{id}/reactions (Add Reaction)
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/messages/{{messageId}}/reactions`
*   **Body (JSON)**:
    ```json
    {
      "emoji": "👍"
    }
    ```

---

### DELETE /messages/{id}/reactions (Remove Reaction)
*   **Method**: `DELETE`
*   **URL**: `{{baseUrl}}/messages/{{messageId}}/reactions`

---

## 4. Friendships & Contacts

### GET /friends (Get Friends List)
*   **Method**: `GET`
*   **URL**: `{{baseUrl}}/friends`

---

### GET /friends/requests/pending (Get Incoming Requests)
*   **Method**: `GET`
*   **URL**: `{{baseUrl}}/friends/requests/pending`

---

### POST /friends/requests (Send Friend Request)
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/friends/requests`
*   **Body (JSON)**:
    ```json
    {
      "friend_id": "64bfcb2a8b3d67f40a1b2c3d"
    }
    ```

---

### PUT /friends/requests/{senderId}/accept (Accept Friend Request)
*   **Method**: `PUT`
*   **URL**: `{{baseUrl}}/friends/requests/{{senderId}}/accept`

---

### DELETE /friends/requests/{senderId}/reject (Reject Friend Request)
*   **Method**: `DELETE`
*   **URL**: `{{baseUrl}}/friends/requests/{{senderId}}/reject`

---

### DELETE /friends/{friendId} (Unfriend User)
*   **Method**: `DELETE`
*   **URL**: `{{baseUrl}}/friends/{{friendId}}`

---

### POST /friends/{friendId}/block (Block User)
*   **Method**: `POST`
*   **URL**: `{{baseUrl}}/friends/{{friendId}}/block`

---

### DELETE /friends/{friendId}/unblock (Unblock User)
*   **Method**: `DELETE`
*   **URL**: `{{baseUrl}}/friends/{{friendId}}/unblock`
