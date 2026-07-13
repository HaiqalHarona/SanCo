# SanCo - End-to-End Encrypted Real-Time Messaging App

SanCo is a modern, high-fidelity real-time messaging application designed with privacy first. Built on top of the Laravel ecosystem and MongoDB, it implements a zero-knowledge End-to-End Encryption (E2EE) architecture. Message contents are encrypted locally in the user's browser and can only be decrypted by the intended participants.

---

## Project Documentation Directory

To navigate the technical details of the SanCo ecosystem, refer to the following specialized documentation guides:

### <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md">End-to-End Encryption Specification</a>
Detailed specification of the zero-knowledge security model and browser cryptography:
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md#1-cryptographic-primitives--technology-stack">Cryptographic Primitives & Tech Stack</a> (libsodium, BIP39, Curve25519, XSalsa20-Poly1305)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md#2-key-management--storage-architecture">Key Management & Storage Architecture</a> (Derivation logic, sessionStorage/localStorage lifetimes, scrubbing)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md#3-the-envelope-encryption-lifecycle">The Envelope Encryption Lifecycle</a> (Encryption/decryption, key wrapping/unwrapping)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md#4-security-architecture-highlights">Security Architecture Highlights</a> (Zero-knowledge, session separation, rotation, hijack prevention)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md#5-key-synchronization--multi-platform-login-workflow">Key Synchronization & Multi-Platform Login</a> (Single-session enforcement, sync lifecycle, constraint warnings)

### <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/relationship_diagram.md">Database Schema & Architecture</a>
Comprehensive design of the database collections, model relationships, and API blueprints:
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/relationship_diagram.md#entity-relationship-diagram">Entity Relationship Diagram</a> (Mermaid visualization of User, Conversation, Message, Friendship collections)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/relationship_diagram.md#model-relationships--functions">Model Relationships & Functions</a> (API/Helpers for User, Conversation, Message, Attachment, Friendship models)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/relationship_diagram.md#database-architecture-overview">Database Architecture & MongoDB Patterns</a> (Embedded arrays, atomic operations, symmetric/reciprocal friendships)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/relationship_diagram.md#proposed-mobile-api-routes-routesapiphp">Mobile API Routes Proposal</a> (Sanctum authentication, keys, conversations, messaging, friendships endpoints)

### <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/api.md">REST API Reference</a>
Technical API testing instructions and JSON request/response schema specifications:
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/api.md#global-setup">Global Setup & Headers Presets</a> (Authorization, content type, environment variables)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/api.md#1-authentication--user-profile">Authentication & Profile Endpoints</a> (GET /user, PUT /user/profile, POST /user/keys/sync)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/api.md#2-conversations--channels">Conversations & Channels Endpoints</a> (GET /conversations, POST /conversations, adding/removing participants)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/api.md#3-messages--e2ee-exchange">Messages & E2EE Exchange Endpoints</a> (Sending encrypted payloads, read-receipts, reaction management)
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/api.md#4-friendships--contacts">Friendships & Contacts Endpoints</a> (Requests, accept/reject, unfriend, blocking/unblocking)

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
  - The server generates a unique **24-word BIP39 Recovery Mnemonic**.
  - A bcrypt-hashed version of this mnemonic is saved on the user's document as the `master_key`.
  - The user is redirected to the `/chat` route, and the unhashed mnemonic is flashed in the session.
  - The browser intercepts the flashed mnemonic, saves it locally as `e2e_recovery_{userId}` in `localStorage`, and derives a Curve25519 keypair.
  - The derived public key is synced and saved to the server via Livewire or the `/api/save-public-key` API route.
- If the user is returning:
  - The server logs them in and redirects them.
  - On page load, the browser retrieves the mnemonic from `localStorage`, derives the Curve25519 keypair, and stores it in `sessionStorage` for active session usage.

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

*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md">End-to-End Encryption Specification (ENCRYPTION.md)</a> — Architectural overview of sodium-based browser cryptography and key distribution.
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/relationship_diagram.md">Database Entity Relationship & Schema Details (relationship_diagram.md)</a> — MongoDB collection designs, relationships, and caching strategies.
*   <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/api.md">REST API Reference (api.md)</a> — HTTP API endpoints overview and testing details.

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

For a deep-dive security breakdown and cryptographic specifications of our end-to-end encryption, check out the <a href="file:///C:/Users/johan/Desktop/Laravel/SanCo/ENCRYPTION.md">ENCRYPTION.md</a> file.
