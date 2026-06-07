import "./bootstrap";
import "./encrypt";
import { Notyf } from "notyf";

window.onlineUsers = [];

// Handle E2E Key derivation on load
document.addEventListener('DOMContentLoaded', async () => {
    const userId = window.userId; 
    if (userId) {
        let privateKey = sessionStorage.getItem('e2e_private_' + userId);
        let publicKey = sessionStorage.getItem('e2e_public_' + userId);

        if (!privateKey || !publicKey) {
            const mnemonic = localStorage.getItem('e2e_recovery_' + userId);
            if (mnemonic) {
                try {
                    const keyPair = await window.EncryptionService.deriveKeyPair(mnemonic);
                    sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                    sessionStorage.setItem('e2e_public_' + userId, keyPair.publicKey);
                    privateKey = keyPair.privateKey;
                    publicKey = keyPair.publicKey;
                    console.log('E2E keys derived and stored in session.');
                } catch (e) {
                    console.error('Failed to derive keys from mnemonic:', e);
                }
            }
        }

        // Sync public key to server if missing
        if (publicKey && !window.userPublicKey) {
            try {
                await fetch('/api/save-public-key', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ public_key: publicKey })
                });
                console.log('Public key synced to server.');
            } catch (e) {
                console.error('Failed to sync public key:', e);
            }
        }
    }
});

// A global instance of Notyf
window.notyf = new Notyf({
    duration: 4000,
    position: {
        x: "right",
        y: "top",
    },
    types: [
        {
            type: "success",
            background: "#ec4899",
            dismissible: true,
        },
        {
            type: "error",
            background: "#ef4444",
            dismissible: true,
        },
    ],
});

document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ respond }) => {
        respond(() => {
            setTimeout(() => {
                const successEl = document.getElementById('wire-session-success');
                const errorEl = document.getElementById('wire-session-error');

                if (successEl && successEl.textContent) {
                    window.notyf.success(successEl.textContent);
                    // CRITICAL: Remove the element so it doesn't fire again on next request
                    successEl.remove();
                }

                if (errorEl && errorEl.textContent) {
                    window.notyf.error(errorEl.textContent);
                    // CRITICAL: Remove the element
                    errorEl.remove();
                }
            }, 50);
        });
    });
});


const triggerPresenceUpdate = () => {
    window.dispatchEvent(new CustomEvent('presence-updated'));
};

document.addEventListener('livewire:init', () => {
    window.Echo.join('presence.chat')
        .here((users) => {
            window.onlineUsers = users.map(user => user.id);
            triggerPresenceUpdate();
        })
        .joining((user) => {
            if (!window.onlineUsers.includes(user.id)) {
                window.onlineUsers.push(user.id);
                triggerPresenceUpdate();
            }
        })
        .leaving((user) => {
            window.onlineUsers = window.onlineUsers.filter(id => id !== user.id);
            triggerPresenceUpdate();
        });
});
