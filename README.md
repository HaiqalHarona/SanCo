# SanCo - End-to-End Encrypted Real-Time Messaging App

SanCo is a modern, high-fidelity real-time messaging application designed with privacy first. Built on top of the Laravel ecosystem and MongoDB, it implements a zero-knowledge End-to-End Encryption (E2EE) architecture. Message contents are encrypted locally in the user's browser and can only be decrypted by the intended participants.

---

## Project Documentation Directory

To navigate the technical details of the SanCo ecosystem, refer to the following specialized documentation guides:

### [End-to-End Encryption Specification](ENCRYPTION.md)
Detailed specification of the zero-knowledge security model and browser cryptography:
*   [Cryptographic Primitives & Tech Stack](ENCRYPTION.md#1-cryptographic-primitives--technology-stack) (libsodium, BIP39, Curve25519, XSalsa20-Poly1305)
*   [Key Management & Storage Architecture](ENCRYPTION.md#2-key-management--storage-architecture) (Derivation logic, sessionStorage/localStorage lifetimes, scrubbing)
*   [The Envelope Encryption Lifecycle](ENCRYPTION.md#3-the-envelope-encryption-lifecycle) (Encryption/decryption, key wrapping/unwrapping)
*   [Security Architecture Highlights](ENCRYPTION.md#4-security-architecture-highlights) (Zero-knowledge, session separation, rotation, hijack prevention)
*   [Key Synchronization & Multi-Platform Login](ENCRYPTION.md#5-key-synchronization--multi-platform-login-workflow) (Single-session enforcement, sync lifecycle, constraint warnings)

### [Database Schema & Architecture](relationship_diagram.md)
Comprehensive design of the database collections, model relationships, and API blueprints:
*   [Entity Relationship Diagram](relationship_diagram.md#entity-relationship-diagram) (Mermaid visualization of User, Conversation, Message, Friendship collections)
*   [Model Relationships & Functions](relationship_diagram.md#model-relationships--functions) (API/Helpers for User, Conversation, Message, Attachment, Friendship models)
*   [Database Architecture & MongoDB Patterns](relationship_diagram.md#database-architecture-overview) (Embedded arrays, atomic operations, symmetric/reciprocal friendships)
*   [Mobile API Routes Proposal](relationship_diagram.md#proposed-mobile-api-routes-routesapiphp) (Sanctum authentication, keys, conversations, messaging, friendships endpoints)

### [REST API Reference](api.md)
Technical API testing instructions and JSON request/response schema specifications:
*   [Global Setup & Headers Presets](api.md#global-setup) (Authorization, content type, environment variables)
*   [Authentication & Profile Endpoints](api.md#1-authentication--user-profile) (GET /user, PUT /user/profile, POST /user/keys/sync)
*   [Conversations & Channels Endpoints](api.md#2-conversations--channels) (GET /conversations, POST /conversations, adding/removing participants)
*   [Messages & E2EE Exchange Endpoints](api.md#3-messages--e2ee-exchange) (Sending encrypted payloads, read-receipts, reaction management)
*   [Friendships & Contacts Endpoints](api.md#4-friendships--contacts) (Requests, accept/reject, unfriend, blocking/unblocking)

---

## Key Features

- **End-to-End Encryption (E2EE)**: Complete zero-knowledge encryption using `libsodium` (XSalsa20-Poly1305 and Curve25519) and BIP39 mnemonics. The server never sees plaintext messages or keys.
- **Real-Time Messaging**: Instant message delivery and typing indicators powered by **Laravel Reverb** (WebSockets) and **Laravel Echo**.
- **Presence Tracking**: Live user online/offline/away status tracking via WebSocket presence channels.
- **Reciprocal Friendship System**: A high-performance mutual friendship model designed for MongoDB, supporting pending requests, acceptances, unfriending, and blocking.
- **Concurrent Login Protection**: Real-time session monitoring. Newer logins automatically invalidate and terminate older active sessions across all devices to prevent unauthorized access.
- **Profile & Avatar Management**: Complete settings dashboard for user profiles, featuring an interactive avatar crop tool (CropperJS) and cryptographic key rotation.
- **Dual-Theme System**: Smooth transitioning between rich dark mode and tailored warm-light mode, persistent across sessions.

---

## Technology Stack

### Backend
- **Framework**: Laravel 12
- **Database**: MongoDB (via `mongodb/laravel-mongodb`)
- **WebSocket Server**: Laravel Reverb
- **Real-Time Client**: Laravel Echo
- **Auth Provider**: Laravel Socialite (Google & GitHub OAuth)
- **PHP Mnemonic Library**: `furqansiddiqui/bip39-mnemonic-php`

### Frontend
- **Reactivity Framework**: Livewire 4 & Volt (Single-file Livewire components)
- **Alpine.js**: Integrated for client-side state, modal overlays, and animations
- **Cryptographic Engine**: `libsodium-wrappers` (WASM-compiled WebAssembly)
- **BIP39 JS Library**: `bip39`
- **Styling**: Tailwind CSS & Vanilla CSS transitions
- **Image Processing**: CropperJS for avatar edits

---

## User Workflow & Lifecycle

The lifecycle of a SanCo user session is split into four key stages:

### 1. Registration & Authentication
- The user logs in via Google or GitHub OAuth.
- If the user is logging in for the first time:
  - The user will be prompted to set a **Sync Password** via a UI modal.
  - The client generates a unique **24-word BIP39 Recovery Mnemonic**.
  - The mnemonic is symmetrically encrypted using AES and the Sync Password, and the ciphertext is saved on the user's document as the `master_key`.
  - The browser derives a Curve25519 keypair from the mnemonic and saves the keys in `sessionStorage` for the active session.
  - The derived public key is synced and saved to the server via Livewire or the `/api/save-public-key` API route.
- If the user is returning:
  - The server logs them in and redirects them.
  - On page load, the user is prompted to enter their **Sync Password**.
  - The browser retrieves the encrypted `master_key` from the database, decrypts it locally with the Sync Password, and stores the recovered mnemonic in `sessionStorage`.

### 2. Friendship Exchange
- Users share their unique `user_tag` (e.g. `SanCo_abc123xyz0`).
- Sending a friend request creates a directional `pending` document in MongoDB.
- Accepting a request creates reciprocal `accepted` documents for both users:
  - `Doc 1: user_id: Alice, friend_id: Bob, status: accepted`
  - `Doc 2: user_id: Bob, friend_id: Alice, status: accepted`
- This reciprocal structure enables high-performance queries for friend lists without complex joins.

### 3. Encrypted Chatting & Key Wrapping
When Alice writes and sends a message to Bob:
- **Client Cache Check**: The client checks `sessionStorage` for participant public keys. If missing, it fetches them via Livewire and caches them under `e2e_keys_{conversationId}`.
- **Symmetric Encryption**: The client generates a random, message-specific 256-bit symmetric key (`msgKey`) and a nonce.
- **Asymmetric Envelope Wrapping**: The client encrypts the message body with the `msgKey`. It then encrypts (seals) the `msgKey` individually for Bob and Alice using their Curve25519 public keys.
- **Transmission**: The client sends the payload `{ encBody, nonce, keys_map }` to the Laravel server. The server stores it in MongoDB and broadcasts it over Reverb.

### 4. Real-time Message Decryption
- Reverb delivers the encrypted payload to Bob's browser in real-time.
- Bob's browser retrieves his Curve25519 private key from `sessionStorage`.
- The browser extracts Bob's sealed `msgKey` from the `keys_map` and unseals it using Bob's private key.
- The browser decrypts the `encBody` using the unsealed `msgKey` and `nonce`, rendering the plaintext message locally.

---

## Repository Structure

Below are the key files and folders in the codebase:

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── SocialController.php           # OAuth flow, Session tracking & Logout logic
│   │   └── Middleware/
│   │       └── DetectConcurrentLogins.php     # Middleware for concurrent session tracking
│   └── Models/
│       ├── User.php                           # User Schema & Relationship Accessors
│       ├── Conversation.php                   # Inbox, Participants & display info helpers
│       ├── Message.php                        # Messages, Read-receipts & reactions
│       ├── Friendship.php                     # Reciprocal friendship & status logic
│       └── Attachment.php                     # Sub-document attachment helpers
│
├── resources/
│   ├── css/
│   │   └── app.css                            # Styling & Tailwind entry point
│   ├── js/
│   │   ├── app.js                             # WebSocket listeners, session check & event bus
│   │   └── encrypt.js                         # E2EE wrapper (derive keys, encrypt, decrypt)
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php                  # Primary application shell & Global configurations
│       └── livewire/
│           ├── auth.blade.php                 # Auth landing page with transitions
│           ├── messenger.blade.php            # Main chat interface with composers & list
│           └── messenger/
│               ├── pending-requests-overlay.blade.php  # Mutual friend request modal
│               └── settings-overlay.blade.php          # User profile settings & key regenerator
│
├── ENCRYPTION.md                              # Deep-dive documentation on E2EE mechanisms
├── relationship_diagram.md                     # Entity-relationship and schema definitions
└── api.md                                     # REST API reference and testing suite
```

---

## Documentation & Reference Guides

To help understand the database architecture, security designs, and backend APIs:

*   [End-to-End Encryption Specification (ENCRYPTION.md)](ENCRYPTION.md) — Architectural overview of sodium-based browser cryptography and key distribution.
*   [Database Entity Relationship & Schema Details (relationship_diagram.md)](relationship_diagram.md) — MongoDB collection designs, relationships, and caching strategies.
*   [REST API Reference (api.md)](api.md) — HTTP API endpoints overview and testing details.

---

## Setup & Local Development

Follow these steps to run SanCo locally:

### 1. Prerequisites
Make sure you have PHP 8.2+, MongoDB running locally or via Atlas, and Node.js installed.

### 2. Environment Configuration
Copy `.env.example` to `.env` and configure your credentials:
```env
APP_NAME=SanCo
APP_ENV=local
APP_KEY=

# MongoDB Connection
MONGODB_URI=mongodb://127.0.0.1:27017/sanco

# Real-time WebSocket Configuration
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME="http"

# OAuth Credentials
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI="${APP_URL}/auth/github/callback"
```

### 3. Bootstrap the Application
Run the automated composer setup script to install dependencies, generate key, migrate, and build assets:
```bash
composer run setup
```

### 4. Boot Dev Services
Run the concurrent dev server script which boots the local server, queue listener, asset compiler, and Reverb server simultaneously:
```bash
composer run dev
```

---

## Security Auditing

For a deep-dive security breakdown and cryptographic specifications of our end-to-end encryption, check out the [ENCRYPTION.md](ENCRYPTION.md) file.
