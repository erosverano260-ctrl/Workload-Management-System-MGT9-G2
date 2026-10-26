/* ui.js — modal controller + styled confirm. Standalone: works with any version of app.js. */
window.UI = {
    open(modal) {
        if (!modal) return;
        // style.css gives <body> a lingering transform, which would make position:fixed
        // relative to the page instead of the screen. Keeping modals directly under <body>
        // plus the body fix in ui.css makes them center on the visible screen.
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
        modal.querySelectorAll('.ui-field, .ui-actions').forEach((el, i) => el.style.setProperty('--i', i));
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ui-lock');
        const first = modal.querySelector('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select');
        if (first) setTimeout(() => first.focus({ preventScroll: true }), 220);
    },

    close(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.ui-modal.is-open')) document.body.classList.remove('ui-lock');
        if (modal.hasAttribute('data-autoopen')) {          // opened via ?edit=ID: tidy the URL
            const u = new URL(window.location.href);
            u.searchParams.delete('edit');
            history.replaceState(null, '', u);
            modal.removeAttribute('data-autoopen');
        }
    },

    /** Styled replacement for window.confirm(). Resolves true/false. */
    confirm(message, okLabel = 'Delete') {
        return new Promise(resolve => {
            const el = document.createElement('div');
            el.className = 'ui-modal ui-confirm';
            el.setAttribute('role', 'alertdialog');
            el.setAttribute('aria-modal', 'true');
            el.innerHTML =
                '<div class="ui-modal-card">' +
                '<header class="ui-modal-head"><span class="ui-modal-icon">!</span><div><h2>Are you sure?</h2></div></header>' +
                '<div class="ui-confirm-body"></div>' +
                '<div class="ui-actions"><button type="button" class="ui-btn" data-no>Cancel</button>' +
                '<button type="button" class="ui-btn danger" data-yes></button></div></div>';
            el.querySelector('.ui-confirm-body').textContent = message;
            el.querySelector('[data-yes]').textContent = okLabel;
            document.body.appendChild(el);

            const done = ok => {
                el.classList.remove('is-open');
                document.removeEventListener('keydown', onKey, true);
                setTimeout(() => { el.remove(); if (!document.querySelector('.ui-modal.is-open')) document.body.classList.remove('ui-lock'); }, 450);
                resolve(ok);
            };
            const onKey = e => { if (e.key === 'Escape') { e.stopPropagation(); done(false); } };
            document.addEventListener('keydown', onKey, true);
            el.querySelector('[data-no]').addEventListener('click', () => done(false));
            el.querySelector('[data-yes]').addEventListener('click', () => done(true));
            el.addEventListener('mousedown', e => { if (e.target === el) done(false); });
            requestAnimationFrame(() => { UI.open(el); el.querySelector('[data-no]').focus(); });
        });
    }
};

/* Forms with data-confirm: intercept in the capture phase so app.js's native confirm() never also fires. */
document.addEventListener('submit', function (e) {
    const f = e.target;
    if (!f || !f.dataset || !f.dataset.confirm || f.dataset.confirmed) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    UI.confirm(f.dataset.confirm, f.dataset.confirmOk || 'Delete').then(ok => {
        if (!ok) return;
        f.dataset.confirmed = '1';
        const b = f.querySelector('button[type=submit]');
        if (b && window.showSpinner) showSpinner(b);
        f.submit();
    });
}, true);

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ui-modal:not(.ui-confirm)').forEach(m => {
        m.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', () => UI.close(m)));
        m.addEventListener('mousedown', e => { if (e.target === m) UI.close(m); });
        if (m.hasAttribute('data-autoopen')) requestAnimationFrame(() => requestAnimationFrame(() => UI.open(m)));
    });
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const open = [...document.querySelectorAll('.ui-modal.is-open:not(.ui-confirm)')].pop();
        if (open) UI.close(open);
    });
    window.addEventListener('pageshow', e => {              // back button: undo stuck states
        if (!e.persisted) return;
        document.querySelectorAll('form[data-confirmed]').forEach(f => delete f.dataset.confirmed);
    });
});
