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
