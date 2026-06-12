import "./bootstrap";
import "./encrypt";
import { Notyf } from "notyf";

window.onlineUsers = [];

// Handle E2E Key derivation on load
document.addEventListener('DOMContentLoaded', async () => {
    const userId = window.userId; 
    if (userId) {
        // Automatically save new master key if provided (silent setup)
        if (window.newMasterKey && !localStorage.getItem('e2e_recovery_' + userId)) {
            const mnemonic = window.newMasterKey;
            localStorage.setItem('e2e_recovery_' + userId, mnemonic);
            console.log('New Master Key saved to localStorage.');

            // Immediately derive and sync public key so encryption works on first message
            try {
                const keyPair = await window.EncryptionService.deriveKeyPair(mnemonic);
                sessionStorage.setItem('e2e_private_' + userId, keyPair.privateKey);
                sessionStorage.setItem('e2e_public_' + userId, keyPair.publicKey);
                
                // Use the Livewire component if available on page, otherwise fallback to fetch
                const messenger = window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (messenger) {
                    await messenger.savePublicKey(keyPair.publicKey);
                    console.log('Public key synced via Livewire.');
                } else {
                    await fetch('/api/save-public-key', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ public_key: keyPair.publicKey })
                    });
                    console.log('Public key synced via API.');
                }
            } catch (e) {
                console.error('Initial key sync failed:', e);
            }
        }

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

        // Sync public key to server if missing OR to ensure consistency
        if (publicKey) {
            try {
                const syncToDB = async (publicKey) => {
                    const messengerEl = document.querySelector('[wire\\:id]');
                    if (messengerEl && window.Livewire) {
                        const messenger = window.Livewire.find(messengerEl.getAttribute('wire:id'));
                        if (messenger) {
                            await messenger.savePublicKey(publicKey);
                            console.log('Public key synced via Livewire.');
                            return true;
                        }
                    }

                    await fetch('/api/save-public-key', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ public_key: publicKey })
                    });
                    console.log('Public key synced via API.');
                    return true;
                };

                // If server doesn't have it, or we just want to be sure
                if (!window.userPublicKey || window.userPublicKey === '') {
                    await syncToDB(publicKey);
                }
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
