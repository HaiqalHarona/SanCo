import * as bip39 from 'bip39';
import sodium from 'libsodium-wrappers';

class EncryptionService {
    async init() {
        if(this.sodium) return; // Already initialized
        await sodium.ready;
        this.sodium = sodium;
    }

    /**
     * Derive a key pair from a BIP39 mnemonic
     * @param {string} mnemonic 
     * @returns {Promise<{publicKey: string, privateKey: string}>}
     */
    async deriveKeyPair(mnemonic) { 
        await this.init();
        const seed = bip39.mnemonicToSeedSync(mnemonic);
        // Use the first 32 bytes of the seed for the key pair
        const seed32 = seed.slice(0, 32);
        const keyPair = this.sodium.crypto_box_seed_keypair(seed32);
        
        return {
            publicKey: this.sodium.to_base64(keyPair.publicKey),
            privateKey: this.sodium.to_base64(keyPair.privateKey)
        };
    }

    /**
     * Encrypt a message for one or more recipients
     * @param {string} body 
     * @param {Object} recipientPublicKeys - {userId: publicKeyBase64}
     * @param {string} senderPrivateKeyBase64
     * @returns {Promise<{encBody: string, keys: Object}>}
     */
    async encryptMessage(body, recipientPublicKeys, senderPrivateKeyBase64) {
        await this.init();
        
        // Generate a random symmetric key
        const msgKey = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_KEYBYTES);
        const nonce = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_NONCEBYTES);
        
        // Encrypt the message body with the symmetric key
        const encBody = this.sodium.crypto_secretbox_easy(body, nonce, msgKey);
        
        // Encrypt the symmetric key for each recipient (using anonymous box for simplicity and privacy)
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

    /**
     * Decrypt a message
     * @param {string} encBodyBase64 
     * @param {string} nonceBase64
     * @param {string} encKeyForMeBase64 
     * @param {string} myPublicKeyBase64
     * @param {string} myPrivateKeyBase64
     * @returns {Promise<string>}
     */
    async decryptMessage(encBodyBase64, nonceBase64, encKeyForMeBase64, myPublicKeyBase64, myPrivateKeyBase64) {
        await this.init();
        
        try {
            const myPublicKey = this.sodium.from_base64(myPublicKeyBase64);
            const myPrivateKey = this.sodium.from_base64(myPrivateKeyBase64);
            const encKeyForMe = this.sodium.from_base64(encKeyForMeBase64);
            
            // Decrypt the symmetric key
            const msgKey = this.sodium.crypto_box_seal_open(encKeyForMe, myPublicKey, myPrivateKey);
            
            // Decrypt the body
            const encBody = this.sodium.from_base64(encBodyBase64);
            const nonce = this.sodium.from_base64(nonceBase64);
            const decryptedBody = this.sodium.crypto_secretbox_open_easy(encBody, nonce, msgKey);
            
            if (!decryptedBody) {
                throw new Error("Secretbox decryption returned null/false");
            }

            return this.sodium.to_string(decryptedBody);
        } catch (e) {
            console.error("Decryption failed", e);
            return "[Decryption Failed]";
        }
    }

    /**
     * Helper to decrypt a message using keys from session storage
     * @param {string} encBody 
     * @param {Object} metadata 
     * @param {string|Object} userId 
     * @returns {Promise<string>}
     */
    async decryptMessageForMe(encBody, metadata, userId) {
        if (!metadata || !metadata.is_encrypted) return encBody;

        // CRITICAL: Handle MongoDB ObjectId serialization if passed as object
        const uid = (typeof userId === 'object' && userId !== null && userId.$oid) 
            ? userId.$oid 
            : String(userId);

        let privateKey = sessionStorage.getItem('e2e_private_' + uid);
        let publicKey = sessionStorage.getItem('e2e_public_' + uid);

        // Auto-recover keypair from localStorage if sessionStorage is empty
        if (!privateKey || !publicKey) {
            const mnemonic = localStorage.getItem('e2e_recovery_' + uid);
            if (mnemonic) {
                try {
                    const keyPair = await this.deriveKeyPair(mnemonic);
                    sessionStorage.setItem('e2e_private_' + uid, keyPair.privateKey);
                    sessionStorage.setItem('e2e_public_' + uid, keyPair.publicKey);
                    privateKey = keyPair.privateKey;
                    publicKey = keyPair.publicKey;
                } catch (e) {
                    console.error('E2E: Auto key recovery failed during decryption:', e);
                }
            }
        }

        // Robust key lookup in enc_keys (handle String vs ObjectId keys)
        let encKeyForMe = metadata.enc_keys?.[uid];
        if (!encKeyForMe && metadata.enc_keys) {
            for (const [k, v] of Object.entries(metadata.enc_keys)) {
                if (String(k) === String(uid)) {
                    encKeyForMe = v;
                    break;
                }
            }
        }

        const nonce = metadata.nonce;

        if (!privateKey || !publicKey || !encKeyForMe || !nonce) {
            console.warn('E2E Decryption skipped: Missing keys/nonce', { 
                uid, 
                hasPrivate: !!privateKey, 
                hasPublic: !!publicKey, 
                hasEncKey: !!encKeyForMe, 
                hasNonce: !!nonce 
            });
            return "[Encrypted Message - Key Missing]";
        }

        return await this.decryptMessage(encBody, nonce, encKeyForMe, publicKey, privateKey);
    }

    /**
     * Encrypt a file binary buffer for one or more recipients
     * @param {ArrayBuffer|Uint8Array} fileBuffer
     * @param {Object} recipientPublicKeys - {userId: publicKeyBase64}
     * @returns {Promise<{encBlobBase64: string, nonce: string, keys: Object}>}
     */
    async encryptFile(fileBuffer, recipientPublicKeys) {
        await this.init();

        const uint8Data = fileBuffer instanceof Uint8Array ? fileBuffer : new Uint8Array(fileBuffer);

        // Generate a random symmetric key & nonce
        const fileKey = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_KEYBYTES);
        const nonce = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_NONCEBYTES);

        // Encrypt the file data
        const encData = this.sodium.crypto_secretbox_easy(uint8Data, nonce, fileKey);

        // Encrypt the symmetric key for each recipient
        const encryptedKeys = {};
        for (const [userId, publicKeyBase64] of Object.entries(recipientPublicKeys)) {
            const publicKey = this.sodium.from_base64(publicKeyBase64);
            const encKey = this.sodium.crypto_box_seal(fileKey, publicKey);
            encryptedKeys[userId] = this.sodium.to_base64(encKey);
        }

        return {
            encBlobBase64: this.sodium.to_base64(encData),
            nonce: this.sodium.to_base64(nonce),
            keys: encryptedKeys
        };
    }

    /**
     * Decrypt a file binary buffer
     * @param {string|Uint8Array} encData 
     * @param {string} nonceBase64 
     * @param {string} encKeyForMeBase64 
     * @param {string} myPublicKeyBase64 
     * @param {string} myPrivateKeyBase64 
     * @returns {Promise<Uint8Array>}
     */
    async decryptFile(encData, nonceBase64, encKeyForMeBase64, myPublicKeyBase64, myPrivateKeyBase64) {
        await this.init();

        const myPublicKey = this.sodium.from_base64(myPublicKeyBase64);
        const myPrivateKey = this.sodium.from_base64(myPrivateKeyBase64);
        const encKeyForMe = this.sodium.from_base64(encKeyForMeBase64);

        // Decrypt the symmetric key
        const fileKey = this.sodium.crypto_box_seal_open(encKeyForMe, myPublicKey, myPrivateKey);

        const encBytes = typeof encData === 'string' ? this.sodium.from_base64(encData) : encData;
        const nonce = this.sodium.from_base64(nonceBase64);
        const decryptedData = this.sodium.crypto_secretbox_open_easy(encBytes, nonce, fileKey);

        if (!decryptedData) {
            throw new Error("File decryption failed");
        }

        return decryptedData;
    }

    /**
     * Helper to decrypt an attachment and create an ObjectURL
     * @param {Object} attachment
     * @param {string|Object} userId
     * @returns {Promise<string>}
     */
    async decryptAttachmentForMe(attachment, userId) {
        if (!attachment || !attachment.encryption_metadata) return attachment.url || '';

        const meta = attachment.encryption_metadata;
        const uid = (typeof userId === 'object' && userId !== null && userId.$oid)
            ? userId.$oid
            : String(userId);

        let privateKey = sessionStorage.getItem('e2e_private_' + uid);
        let publicKey = sessionStorage.getItem('e2e_public_' + uid);

        if (!privateKey || !publicKey) {
            const mnemonic = localStorage.getItem('e2e_recovery_' + uid);
            if (mnemonic) {
                try {
                    const keyPair = await this.deriveKeyPair(mnemonic);
                    sessionStorage.setItem('e2e_private_' + uid, keyPair.privateKey);
                    sessionStorage.setItem('e2e_public_' + uid, keyPair.publicKey);
                    privateKey = keyPair.privateKey;
                    publicKey = keyPair.publicKey;
                } catch (e) {
                    console.error('E2E: Auto key recovery failed during attachment decryption:', e);
                }
            }
        }

        let encKeyForMe = meta.enc_keys?.[uid];
        if (!encKeyForMe && meta.enc_keys) {
            for (const [k, v] of Object.entries(meta.enc_keys)) {
                if (String(k) === String(uid)) {
                    encKeyForMe = v;
                    break;
                }
            }
        }

        const nonce = meta.nonce;
        if (!privateKey || !publicKey || !encKeyForMe || !nonce) {
            throw new Error("Missing keys to decrypt attachment");
        }

        // Fetch the raw ciphertext blob from server/MinIO
        const downloadUrl = attachment.url || ('/storage/' + attachment.storage_path);
        const response = await fetch(downloadUrl);
        const arrayBuffer = await response.arrayBuffer();
        const decryptedBytes = await this.decryptFile(new Uint8Array(arrayBuffer), nonce, encKeyForMe, publicKey, privateKey);

        const blob = new Blob([decryptedBytes], { type: attachment.mime_type || 'application/octet-stream' });
        return URL.createObjectURL(blob);
    }
}

window.EncryptionService = new EncryptionService();
