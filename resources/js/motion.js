import { animate, stagger } from "animejs";

window.animeAnimate = animate;
window.animeStagger = stagger;

export const initMagneticButtons = (selector = '.btn-magnetic') => {
    document.querySelectorAll(selector).forEach(btn => {
        if (btn.dataset.magneticInit) return;
        btn.dataset.magneticInit = 'true';

        btn.addEventListener('mousemove', (e) => {
            const rect = btn.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;

            animate(btn, {
                translateX: x * 0.25,
                translateY: y * 0.25,
                duration: 300,
                ease: 'outQuad'
            });
        });

        btn.addEventListener('mouseleave', () => {
            animate(btn, {
                translateX: 0,
                translateY: 0,
                duration: 600,
                ease: 'outElastic(1, .5)'
            });
        });
    });
};

export const animateClick = (target) => {
    animate(target, {
        scale: [0.93, 1],
        duration: 400,
        ease: 'outQuad'
    });
};

export const animateMessageCascade = (selector = '.message-bubble-anim') => {
    const els = document.querySelectorAll(selector);
    if (!els.length) return;

    animate(els, {
        opacity: [0, 1],
        translateY: [16, 0],
        scale: [0.97, 1],
        delay: stagger(40, { start: 50 }),
        ease: 'outQuad'
    });
};

export const animateHeroChips = (selector = '.hero-badge-chip') => {
    const els = document.querySelectorAll(selector);
    if (!els.length) return;

    animate(els, {
        translateY: [-6, 6],
        duration: 3000,
        ease: 'inOutSine',
        direction: 'alternate',
        loop: true,
        delay: stagger(250)
    });
};

export const animateOAuthUnlock = (selector = '.oauth-btn') => {
    const els = document.querySelectorAll(selector);
    if (!els.length) return;

    animate(els, {
        scale: [0.94, 1],
        opacity: [0.4, 1],
        duration: 500,
        ease: 'outQuad'
    });
};

export const animateMessageSlideIn = (target) => {
    if (!target) return;
    const els = typeof target === 'string' ? document.querySelectorAll(target) : (target instanceof NodeList || Array.isArray(target) ? Array.from(target) : [target]);
    if (!els.length) return;

    els.forEach(el => {
        if (!el || el.dataset.animatedMsg) return;
        el.dataset.animatedMsg = 'true';

        animate(el, {
            opacity: [0, 1],
            translateY: [24, 0],
            scale: [0.96, 1],
            duration: 450,
            ease: 'outCubic'
        });
    });
};

export const animateModalEntry = (modalCardSelector) => {
    const card = typeof modalCardSelector === 'string' ? document.querySelector(modalCardSelector) : modalCardSelector;
    if (!card) return;

    animate(card, {
        opacity: [0, 1],
        scale: [0.9, 1],
        translateY: [20, 0],
        duration: 450,
        ease: 'outQuad'
    });
};

export const animateHoverPill = (pillEl, targetEl) => {
    if (!pillEl || !targetEl) return;
    const targetRect = targetEl.getBoundingClientRect();
    const container = pillEl.parentElement;
    if (!container) return;
    const containerRect = container.getBoundingClientRect();

    const top = targetRect.top - containerRect.top + container.scrollTop;
    const height = targetRect.height;
    const width = targetRect.width;
    const left = targetRect.left - containerRect.left;

    animate(pillEl, {
        top: top,
        height: height,
        left: left,
        width: width,
        opacity: 1,
        duration: 300,
        ease: 'outCubic'
    });
};

export const hideHoverPill = (pillEl) => {
    if (!pillEl) return;
    animate(pillEl, {
        opacity: 0,
        duration: 220,
        ease: 'outQuad'
    });
};

// Global init helper
document.addEventListener('DOMContentLoaded', () => {
    initMagneticButtons();
});

window.SanCoMotion = {
    initMagneticButtons,
    animateClick,
    animateMessageCascade,
    animateMessageSlideIn,
    animateHeroChips,
    animateOAuthUnlock,
    animateModalEntry,
    animateHoverPill,
    hideHoverPill
};
