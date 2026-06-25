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

// Escape helper for HTML safety
const escapeHtml = (str) => {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};

// Show a modal alert popup for multiple sessions
const showPopup = (title, message, type = 'error', avatarUrl = null) => {
    if (document.getElementById('custom-alert-popup')) {
        return;
    }

    let parsedLocation = null;
    let parsedBrowser = null;
    let mainMessage = message;

    if (type === 'error' && message.includes('Another login detected')) {
        const match = message.match(/Another login detected\. You have been logged out\. New login from:\s*(.*?)\s*using\s*(.*?)\.?$/);
        if (match) {
            mainMessage = "Another active session was initiated. To protect your messages, this session has been terminated.";
            parsedLocation = match[1];
            parsedBrowser = match[2];
        }
    }

    const popupHtml = `
        <div id="custom-alert-popup" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 select-none">
            <!-- Backdrop -->
            <div id="popup-backdrop" class="absolute inset-0 bg-black/75 backdrop-blur-md opacity-0 transition-opacity duration-300 ease-out"></div>
            
            <!-- Card -->
            <div id="popup-card" class="relative w-full max-w-md bg-[#1a1a1e] border border-white/[0.08] rounded-2xl p-6 shadow-[0_0_50px_rgba(0,0,0,0.8)] text-center overflow-hidden transform scale-90 opacity-0 transition-all duration-300 ease-out flex flex-col items-center">
                
                <!-- Subtle Gradient Glow Top Accent -->
                <div class="absolute top-0 inset-x-0 h-[3px] bg-gradient-to-r ${type === 'error' ? 'from-rose-500 via-pink-500 to-red-500' : 'from-emerald-500 via-teal-500 to-green-500'}"></div>
                
                <!-- Pfp container -->
                <div class="mb-5 flex items-center justify-center w-24 h-24 rounded-full bg-white/5 border border-white/10 p-1 shadow-lg shadow-black/35 backdrop-blur-sm overflow-hidden shrink-0">
                    <img src="${avatarUrl || '/images/fallback-image/fallback.png'}" 
                         onerror="this.onerror=null; this.src='/images/fallback-image/fallback.png';" 
                         class="w-full h-full object-cover rounded-full" 
                         alt="User Avatar">
                </div>

                <!-- Title -->
                <h3 class="text-white text-xl font-extrabold tracking-tight mb-2">
                    ${escapeHtml(title)}
                </h3>

                <!-- Message -->
                <p class="text-white/60 text-sm leading-relaxed mb-4 max-w-sm">
                    ${escapeHtml(mainMessage)}
                </p>

                <!-- Detailed metadata panel (for concurrent login) -->
                ${parsedLocation || parsedBrowser ? `
                    <div class="w-full text-left bg-black/40 border border-white/[0.04] rounded-xl p-4 mb-6 space-y-2.5 font-sans">
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-white/35 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div>
                                <div class="text-[10px] text-white/40 uppercase tracking-wider font-semibold">New Login Location</div>
                                <div class="text-white/80 text-sm font-medium mt-0.5">${escapeHtml(parsedLocation)}</div>
                            </div>
                        </div>
                        <div class="h-px bg-white/[0.04] w-full"></div>
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-white/35 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <div class="text-[10px] text-white/40 uppercase tracking-wider font-semibold">Device & Browser</div>
                                <div class="text-white/80 text-sm font-medium mt-0.5">${escapeHtml(parsedBrowser)}</div>
                            </div>
                        </div>
                    </div>
                ` : ''}

                <!-- Button -->
                <button id="close-popup-btn" class="w-full py-3 px-5 rounded-xl text-sm font-bold text-white transition-all duration-300 ease-out select-none cursor-pointer focus:outline-none focus:ring-2
                    ${type === 'error' 
                        ? 'bg-gradient-to-r from-rose-500/20 to-pink-500/20 hover:from-rose-500/35 hover:to-pink-500/35 active:scale-[0.98] border border-rose-500/30 focus:ring-rose-500/40' 
                        : 'bg-gradient-to-r from-emerald-500/20 to-teal-500/20 hover:from-emerald-500/35 hover:to-teal-500/35 active:scale-[0.98] border border-emerald-500/30 focus:ring-emerald-500/40'}">
                    Acknowledge
                </button>
            </div>
        </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = popupHtml.trim();
    const popupElement = div.firstChild;
    document.body.appendChild(popupElement);

    const backdrop = document.getElementById('popup-backdrop');
    const card = document.getElementById('popup-card');
    const btn = document.getElementById('close-popup-btn');

    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        backdrop.classList.add('opacity-100');
        card.classList.remove('scale-90', 'opacity-0');
        card.classList.add('scale-100', 'opacity-100');
    });

    const closePopup = () => {
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        card.classList.remove('scale-100', 'opacity-100');
        card.classList.add('scale-90', 'opacity-0');

        setTimeout(() => {
            popupElement.remove();
        }, 300);
    };

    btn.addEventListener('click', closePopup);
    backdrop.addEventListener('click', closePopup);
};

// Check for initial session flash messages on page load
const showFlashMessages = () => {
    const successEl = document.getElementById('wire-session-success');
    const errorEl = document.getElementById('wire-session-error');

    if (successEl && successEl.textContent.trim()) {
        window.notyf.success(successEl.textContent.trim());
        successEl.remove();
    }

    if (errorEl && errorEl.textContent.trim()) {
        const errorMsg = errorEl.textContent.trim();
        const avatarUrl = errorEl.getAttribute('data-avatar');
        if (errorMsg.includes('Another login detected')) {
            showPopup('Session Terminated', errorMsg, 'error', avatarUrl);
        } else {
            window.notyf.error(errorMsg);
        }
        errorEl.remove();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showFlashMessages);
} else {
    showFlashMessages();
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ respond }) => {
        respond(() => {
            setTimeout(() => {
                const successEl = document.getElementById('wire-session-success');
                const errorEl = document.getElementById('wire-session-error');

                if (successEl && successEl.textContent) {
                    window.notyf.success(successEl.textContent.trim());
                    successEl.remove();
                }

                if (errorEl && errorEl.textContent) {
                    const errorMsg = errorEl.textContent.trim();
                    const avatarUrl = errorEl.getAttribute('data-avatar');
                    if (errorMsg.includes('Another login detected')) {
                        showPopup('Session Terminated', errorMsg, 'error', avatarUrl);
                    } else {
                        window.notyf.error(errorMsg);
                    }
                    errorEl.remove();
                }
            }, 50);
        });
    });
});


const triggerPresenceUpdate = () => {
    window.dispatchEvent(new CustomEvent('presence-updated'));
};

window.addEventListener('logout', () => {
    const userId = window.userId;
    if (userId) {
        localStorage.removeItem('e2e_recovery_' + userId);
        sessionStorage.removeItem('e2e_private_' + userId);
        sessionStorage.removeItem('e2e_public_' + userId);
    }
    // Also clear general keys just in case
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key.startsWith('e2e_recovery_')) localStorage.removeItem(key);
    }
    for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key.startsWith('e2e_private_') || key.startsWith('e2e_public_')) sessionStorage.removeItem(key);
    }
});

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
