# End-to-End Encryption (E2EE) System Documentation

SanCo implements a secure, zero-knowledge End-to-End Encryption (E2EE) system. This document outlines the cryptographic primitives, key management, message transport lifecycle, and security design details of the system.

---

## 1. Cryptographic Primitives & Technology Stack

The application uses standard, proven cryptographic algorithms via high-performance libraries to secure all communications:

- **Cryptographic Engine**: [`libsodium-wrappers`](https://github.com/jedisct1/libsodium-js) (WebAssembly-compiled Libsodium).
- **Key Derivation (KDF)**: BIP39 standard (24-word recovery phrase) for user-friendly mnemonic generation and keypair derivation.
- **Asymmetric Key Exchange / Key Wrapping**: Curve25519 anonymous sealed boxes (`crypto_box_seal`) to securely transmit symmetric keys to multiple recipients without exposing sender identity.
- **Symmetric Encryption**: XSalsa20 stream cipher combined with Poly1305 MAC (`crypto_secretbox_easy`) for high-speed, authenticated symmetric message encryption.
- **Polyfills**: `buffer` polyfill (required for BIP39 mnemonic validation and synchronization in browser environments).

---

## 2. Key Management & Storage Architecture

SanCo manages keys across three distinct layers, balancing security, convenience, and performance:

```
+----------------------------------------------------------------------------+
|                          1. Master Mnemonic                                |
|  - 24-word BIP39 Recovery Phrase                                           |
|  - Encrypted with user's Sync Password (AES) before sending to DB          |
|  - Stored in MongoDB ('master_key' field)                                  |
|  - Temporarily decrypted into sessionStorage ('e2e_recovery_{userId}')     |
+----------------------------------------------------------------------------+
                                      |
                                      v (Derivation)
+----------------------------------------------------------------------------+
|                       2. Active Session Keypair                            |
|  - Derived on page load / session initialization                           |
|  - Curve25519 Public Key (Uploaded to MongoDB)                             |
|  - Curve25519 Private Key (Stays in sessionStorage: 'e2e_private_{userId}')|
+----------------------------------------------------------------------------+
                                      |
                                      v (Message Send)
+----------------------------------------------------------------------------+
|                       3. Message-Specific Symmetric Key                    |
|  - Ephemeral 256-bit symmetric key (randomly generated per-message)        |
|  - Never written to persistent storage                                     |
|  - Encrypted for each recipient using their Curve25519 Public Key          |
+----------------------------------------------------------------------------+
```

### 2.1 Key Derivation Logic (`resources/js/encrypt.js`)
When a user logs in or regenerates their keys, the client derives the keypair from their 24-word BIP39 recovery phrase:

```javascript
async deriveKeyPair(mnemonic) { 
    await this.init();
    // 1. Convert the 24-word phrase into a 512-bit seed
    const seed = bip39.mnemonicToSeedSync(mnemonic);
    // 2. Extract the first 32 bytes (256 bits) to use as the seed for Curve25519
    const seed32 = seed.slice(0, 32); 
    // 3. Derive public and private keypair
    const keyPair = this.sodium.crypto_box_seed_keypair(seed32);
    
    return {
        publicKey: this.sodium.to_base64(keyPair.publicKey),
        privateKey: this.sodium.to_base64(keyPair.privateKey)
    };
}
```

### 2.2 Storage Lifetimes & Isolation
- **`sessionStorage`**: Stores `e2e_recovery_{userId}` (the plaintext BIP39 mnemonic) and the derived `e2e_private_{userId}` / `e2e_public_{userId}` keys (Base64-encoded). This ensures that sensitive cryptographic materials exist only during the lifetime of the browser tab and are destroyed when the tab is closed.
- **`sessionStorage` Cache**: Stores cached participant public keys under `e2e_keys_{conversationId}` to prevent costly database queries and API calls on every keystroke.
- **Database (MongoDB)**: Stores the user's `master_key` which is their 24-word phrase encrypted symmetrically using a Sync Password. The server cannot decrypt this.

### 2.3 Automated Session Scrubbing (Logout / Terminate)
To prevent key leakage across multiple users on shared devices, any logout action or session termination triggers a browser-wide event to scrub all E2E artifacts:

```javascript
window.addEventListener('logout', () => {
    const userId = window.userId;
    if (userId) {
        sessionStorage.removeItem('e2e_recovery_' + userId);
        sessionStorage.removeItem('e2e_private_' + userId);
        sessionStorage.removeItem('e2e_public_' + userId);
    }
    // Scrub all E2E session storage artifacts to prevent cross-account leakage
    const ssKeysToRemove = [];
    for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key && (key.startsWith('e2e_recovery_') || key.startsWith('e2e_private_') || key.startsWith('e2e_public_') || key.startsWith('e2e_keys_'))) {
            ssKeysToRemove.push(key);
        }
    }
    ssKeysToRemove.forEach(k => sessionStorage.removeItem(k));
});
```

### 2.4 Cryptographic Keys Demystified: Role and Locking Mechanics

To understand how the messaging content is locked (encrypted) and unlocked (decrypted), it is helpful to look at the roles and locations of the four cryptographic keys involved:

| Key Type | What It Is | Stored Location | Locking / Unlocking Mechanics |
| :--- | :--- | :--- | :--- |
| **Master Recovery Key**<br>*(BIP39 Mnemonic)* | A human-readable 24-word phrase (e.g., `apple banana ...`). | **Client**: Browser `sessionStorage` (`e2e_recovery_{userId}`).<br>**Server**: Encrypted ciphertext using Sync Password (`master_key`). | **Derivation Key**: Not used to encrypt messages directly. Instead, it is the root seed from which the client derives the asymmetric Curve25519 keypair. |
| **Curve25519 Public Key**<br>*(Asymmetric Key)* | A Base64-encoded representation of the Curve25519 public key. | **Client**: Browser memory / `sessionStorage`.<br>**Server**: Saved publicly in MongoDB on the User document (`public_key`). | **The Lock**: Shared publicly. When a sender transmits a message, their browser uses the recipient's **Public Key** to wrap and lock the message-specific symmetric key. |
| **Curve25519 Private Key**<br>*(Asymmetric Key)* | A Base64-encoded representation of the Curve25519 private key. | **Client**: Browser `sessionStorage` (`e2e_private_{userId}`).<br>**Server**: **Never sent to the server.** | **The Unlocking Key**: Kept private. When you receive a message, your browser uses your **Private Key** to open the wrapped envelope and retrieve the symmetric key. |
| **Message Symmetric Key**<br>*(`msgKey` / Ephemeral)* | A randomly generated 256-bit symmetric key. | **Client**: Exists only in transient memory during message processing. **Never stored.** | **Content Lock & Unlock**: Used to symmetrically encrypt the plaintext message body on sending, and decrypt the ciphertext back to plaintext on receiving. |

---

## 3. The Envelope Encryption Lifecycle

To support multi-recipient chats (groups) and preserve maximum privacy, SanCo uses **Envelope Encryption** (Hybrid Cryptography). 

```
                                  MESSAGE ENVELOPE CREATION
                                  
   Plaintext Message ──────> [ Symmetric Encrypter ] ──────────> Encrypted Ciphertext
                                     ▲
                                     │ (msgKey)
                              [ Random Generator ]
                                     │
                                     ├─────────────────────────┐
                                     │ (msgKey)                │ (msgKey)
                                     ▼                         ▼
   Bob's Public Key  ───> [ Curve25519 Seal ]        Alice's Public Key ───> [ Curve25519 Seal ]
                                 │                                               │
                                 ▼                                               ▼
                         Bob's Wrapped Key                               Alice's Wrapped Key
```

### 3.1 Encryption & Key Wrapping (`resources/js/encrypt.js`)
When Alice sends a message in a conversation containing herself and Bob:

1. A random 256-bit symmetric key (`msgKey`) and a 192-bit initialization vector (`nonce`) are generated.
2. The message body is encrypted symmetrically using the `msgKey`.
3. The `msgKey` is encrypted (sealed) individually using Bob's public key and Alice's public key.
4. Only the ciphertext, nonce, and the wrapped keys map are sent to the server.

```javascript
async encryptMessage(body, recipientPublicKeys, senderPrivateKeyBase64) {
    const msgKey = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_KEYBYTES);
    const nonce = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_NONCEBYTES);
    
    // Encrypt message symmetrically
    const encBody = this.sodium.crypto_secretbox_easy(body, nonce, msgKey);
    
    // Seal symmetric key for each participant (using Anonymous Sealed Box)
    const encryptedKeys = {};
    for (const [userId, publicKeyBase64] of Object.entries(recipientPublicKeys)) {
        const publicKey = this.sodium.from_base64(publicKeyBase64);
        const encKey = this.sodium.crypto_box_seal(msgKey, publicKey);
        encryptedKeys[userId] = this.sodium.to_base64(encKey);
    }

    return { 
        encBody: this.sodium.to_base64(encBody), 
        nonce: this.sodium.to_base64(nonce), 
        keys: encryptedKeys 
    };
}
```

### 3.2 Decryption & Key Unwrapping (`resources/js/encrypt.js`)
When Bob receives the message package from the server:

1. Bob extracts his wrapped key envelope using his user ID: `metadata.enc_keys[Bob's ID]`.
2. Bob opens the sealed box using his Curve25519 private key to recover the symmetric `msgKey`.
3. Bob decrypts the ciphertext using the recovered `msgKey` and the message's `nonce`.

```javascript
async decryptMessage(encBodyBase64, nonceBase64, encKeyForMeBase64, myPublicKeyBase64, myPrivateKeyBase64) {
    await this.init();
    try {
        const myPublicKey = this.sodium.from_base64(myPublicKeyBase64);
        const myPrivateKey = this.sodium.from_base64(myPrivateKeyBase64);
        const encKeyForMe = this.sodium.from_base64(encKeyForMeBase64);
        
        // Unseal the message-specific symmetric key
        const msgKey = this.sodium.crypto_box_seal_open(encKeyForMe, myPublicKey, myPrivateKey);
        
        // Decrypt the ciphertext with the recovered symmetric key
        const encBody = this.sodium.from_base64(encBodyBase64);
        const nonce = this.sodium.from_base64(nonceBase64);
        const decryptedBody = this.sodium.crypto_secretbox_open_easy(encBody, nonce, msgKey);
        
        return this.sodium.to_string(decryptedBody);
    } catch (e) {
        console.error("Decryption failed", e);
        return "[Decryption Failed]";
    }
}
```

---

## 4. Security Architecture Highlights

- **Zero-Knowledge Architecture**: The server acts as a blind mailbox. Plaintext message bodies and private keys are never transmitted to the network. The server only sees base64-encoded ciphertexts and sealed envelopes.
- **Anonymous Key Sealing (`crypto_box_seal`)**: Curve25519 sealed boxes do not contain public keys or signatures that expose the sender's identity to third parties. They are anonymous, ensuring that eavesdroppers cannot determine which key was used to wrap the envelope.
- **Session Separation via Multi-Tab Storage**: Derived keys reside in `sessionStorage`. If a user opens a new tab, the keys are securely derived again from the mnemonic, but if they log out, the session storage is instantly destroyed.
- **Key Rotation Support**: Users can generate a new BIP39 recovery phrase from the settings overlay. This immediately derives a new Curve25519 keypair, uploads the new public key, and updates the local storage, rotating the user's active key. (Note: historical messages will remain encrypted with the previous keys and cannot be decrypted without importing the old recovery phrase).
- **Session Hijack Prevention**: The `DetectConcurrentLogins` middleware continuously checks the active session ID against the database. If a new session is logged in from a different IP/Location/browser, the old session is automatically invalidated, and its E2E keys are scrubbed immediately.

---

## 5. Key Synchronization & Multi-Platform Login Workflow

Because SanCo is a zero-knowledge E2EE application, the server never stores or transmits E2E private keys or unhashed recovery mnemonics. When a user transitions between browsers, platforms, or devices, keys must be re-established. 

The application utilizes a **single-session enforcement model** combined with **on-demand key synchronization** to handle cross-platform transitions securely and prevent key synchronization conflicts.

### 5.1 The Multi-Platform Login Lifecycle

```
[ User logs into Browser B ]
            │
            ▼
┌────────────────────────────────────────────────────────┐
│ 1. Concurrent Session Invalidation (Server-side)      │
│    - DetectConcurrentLogins middleware triggers.       │
│    - Logs out Browser A instantly.                    │
│    - Triggers 'logout' event on Browser A to scrub    │
│      its localStorage and sessionStorage E2E keys.      │
└────────────────────────────────────────────────────────┘
            │
            ▼
┌────────────────────────────────────────────────────────┐
│ 2. E2E State Initialization on Browser B               │
│    - Browser B has empty localStorage and E2E keys.    │
│    - UI displays "Standard (Waiting for keys)".        │
└────────────────────────────────────────────────────────┘
            │
            ├────────────────────────────────────────────┐
            ▼ (Option A: Restore Existing Key)          ▼ (Option B: Rotate & Generate New Key)
┌───────────────────────────────────────────────────┐    ┌───────────────────────────────────────────────────┐
│ 3a. User enters Sync Password in UI Modal.       │    │ 3b. User sets Sync Password with strength check   │
│ 4a. Browser retrieves encrypted master_key from   │    │     and confirmation in UI Modal.                 │
│     DB and decrypts phrase using Sync Password.   │    │ 4b. Client generates 24-word BIP39 mnemonic.      │
│ 5a. Browser derives Curve25519 keypair and caches │    │ 5b. Client encrypts phrase via AES with password  │
│     private key in sessionStorage.                │    │     and saves encrypted ciphertext to MongoDB.    │
│ 6a. Browser checks/saves public key consistency   │    │ 6b. Client derives keypair and syncs public key   │
│     with the server.                              │    │     to MongoDB and keypair to sessionStorage.    │
└───────────────────────────────────────────────────┘    └───────────────────────────────────────────────────┘
            │                                                                │
            └────────────────────────────────┬───────────────────────────────┘
                                             │
                                             ▼
┌────────────────────────────────────────────────────────┐
│ 7. Peer Sync                                           │
│    - Other users' active client key caches detect the  │
│      new public key.                                   │
│    - Future messages sent to the user are wrapped      │
│      using this new public key.                        │
└────────────────────────────────────────────────────────┘
```

### 5.2 Key Steps in the Synchronisation Process

1. **Enforcing One Session (Preventing Conflicts)**:
   When the user logs into a new browser (Browser B), the server updates `current_session_id` on the user's document in MongoDB. The next time the user's old browser (Browser A) makes a request, the `DetectConcurrentLogins` middleware terminates the session, logs out the user, and redirects to the landing page. Crucially, Browser A's JS runtime intercepts this logout and cleanses all localStorage and sessionStorage keys. This prevents two active browsers from encrypting/decrypting messages under competing sessions or inconsistent keys.

2. **Restoring the Key (Browser B Setup)**:
   Since Browser B has no E2E keys initially, the user is locked out of messaging features. The user can either:
   - **Unlock Existing Key**: Enter Sync Password in the UI Modal (with real-time password reveal support). The browser fetches the encrypted `master_key` ciphertext from MongoDB, decrypts it locally using AES with the Sync Password, recovers the 24-word phrase into `sessionStorage`, and derives the Curve25519 keypair. E2EE is fully restored, preserving readability for historical messages.
   - **Generate New Key (Key Rotation)**: Click **Generate New Key** in the settings panel. This prompts the user to set a new Sync Password (featuring live password strength meter and confirm field validation). The client generates a new 24-word BIP39 recovery mnemonic, derives the new Curve25519 keypair, caches the private key and mnemonic in `sessionStorage`, symmetrically encrypts the mnemonic using AES with the Sync Password, automatically uploads the encrypted ciphertext to `master_key` in MongoDB, and syncs the new public key.

   > [!WARNING]
   > **Historical Message Readability Constraint:**
   > If the user chooses to **Generate a New Key** (Option B) on a new device/browser instead of unlocking their existing key:
   > 1. A new asymmetric keypair is generated. The new private key is mathematically unrelated to the previous one.
   > 2. Historical messages that were sealed using the *old* public key **cannot** be decrypted using the *new* private key.
   > 3. Consequently, the user will lose the ability to read historical messages on that device.
   > 4. To maintain access to old messages, the user **must** remember their Sync Password, or import their original 24-word Master Recovery Key.

3. **Peer Key Cache Invalidation**:
   Peers sending messages check `sessionStorage` first for recipient public keys. When a user syncs a new public key to the server, the peer clients detect a mismatch or update on their next cache-miss check. They re-fetch the updated public key via the Livewire component `$wire.getParticipantKeys()`, update their local caches, and wrap subsequent message envelopes with the user's updated public key.

