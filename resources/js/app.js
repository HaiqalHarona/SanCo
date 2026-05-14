import "./bootstrap";
import { Notyf } from "notyf";
import * as bip39 from 'bip39';
import sodium from "libsodium-wrappers";

window.sodium = sodium;

window.onlineUsers = [];

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