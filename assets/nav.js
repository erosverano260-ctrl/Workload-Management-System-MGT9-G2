/* nav.js — collapsible main sidebar. State lives on <html class="nav-collapsed"> and is remembered. */
(function () {
    const root = document.documentElement, btn = document.getElementById('navToggle');
    document.querySelectorAll('.sidebar nav a').forEach(a => { a.title = a.textContent.trim(); });   // names show on hover when icon-only
    if (!btn) return;
    function sync() {
        const c = root.classList.contains('nav-collapsed');
        btn.setAttribute('aria-expanded', String(!c));
        btn.title = c ? 'Show menu' : 'Hide menu';
        btn.setAttribute('aria-label', btn.title);
    }
    sync();
    btn.addEventListener('click', function () {
        root.classList.toggle('nav-collapsed');
        try { localStorage.setItem('navCollapsed', root.classList.contains('nav-collapsed') ? '1' : '0'); } catch (e) {}
        sync();
    });
})();

/* live unread-notification badge: refreshes every 30 s without reloading the page */
(function () {
    const badges = document.querySelectorAll('[data-notif-badge]');
    if (!badges.length) return;
    function tick() {
        fetch('notifications.php?count=1', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(j => badges.forEach(b => { b.textContent = j.unread; b.hidden = !j.unread; }))
            .catch(() => {});
    }
    setInterval(tick, 30000);
})();
