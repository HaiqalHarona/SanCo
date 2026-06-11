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
        
        // 1. Generate a random symmetric key
        const msgKey = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_KEYBYTES);
        const nonce = this.sodium.randombytes_buf(this.sodium.crypto_secretbox_NONCEBYTES);
        
        // 2. Encrypt the message body with the symmetric key
        const encBody = this.sodium.crypto_secretbox_easy(body, nonce, msgKey);
        
        // 3. Encrypt the symmetric key for each recipient (using anonymous box for simplicity and privacy)
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
            
            // 1. Decrypt the symmetric key
            const msgKey = this.sodium.crypto_box_seal_open(encKeyForMe, myPublicKey, myPrivateKey);
            
            // 2. Decrypt the body
            const encBody = this.sodium.from_base64(encBodyBase64);
            const nonce = this.sodium.from_base64(nonceBase64);
            const decryptedBody = this.sodium.crypto_secretbox_open_easy(encBody, nonce, msgKey);
            
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
     * @param {string} userId 
     * @returns {Promise<string>}
     */
    async decryptMessageForMe(encBody, metadata, userId) {
        if (!metadata || !metadata.is_encrypted) return encBody;

        const privateKey = sessionStorage.getItem('e2e_private_' + userId);
        const publicKey = sessionStorage.getItem('e2e_public_' + userId);
        const encKeyForMe = metadata.enc_keys?.[userId];
        const nonce = metadata.nonce;

        if (!privateKey || !publicKey || !encKeyForMe || !nonce) {
            return "[Encrypted Message - Key Missing]";
        }

        return await this.decryptMessage(encBody, nonce, encKeyForMe, publicKey, privateKey);
    }
}

window.EncryptionService = new EncryptionService();
