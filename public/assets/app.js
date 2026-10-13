// assets/app.js — StreamList front-end behavior

document.addEventListener('DOMContentLoaded', () => {

    // ── Profile dropdown toggle ──────────────────────────────
    // JS manages state (the .open class), CSS renders it —
    // the same division of labor as the original StreamList.
    document.querySelectorAll('.profile-menu').forEach((menu) => {
        const btn = menu.querySelector('.profile-btn');
        if (!btn) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();              // don't let this click also
            menu.classList.toggle('open');    // trigger the document handler below
        });
    });

    // Click anywhere else → close all dropdowns
    document.addEventListener('click', () => {
        document.querySelectorAll('.profile-menu.open').forEach((menu) => {
            menu.classList.remove('open');
        });
    });

    // ── Confirm dialogs (your onsubmit="return confirm(...)" pattern) ──
    // Left inline on the forms via the onsubmit attribute — nothing needed
    // here. This file is the home for anything that outgrows one-liners.

    // ── Future: AJAX watchlist button (Stage 4+, when built) ──
});