// Shared front-end interactions & animations for the EE Enrollment Portal.

document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Enroll-student modal (courses.php) ---------- */
    const modal = document.getElementById('enrollModal');
    const backdrop = document.getElementById('enrollBackdrop');
    if (modal) {
        const closeBtn = document.getElementById('closeModal');
        const form = document.getElementById('enrollForm');

        function openModal(data) {
            document.getElementById('modalCourse').textContent = data.name;
            document.getElementById('modalCode').textContent = data.code + ' · ' + data.units + ' units';
            document.getElementById('modalInstructor').textContent = data.instructor;
            document.getElementById('modalSchedule').textContent = data.schedule;
            document.getElementById('modalRoom').textContent = data.room;
            document.getElementById('modalCourseCode').value = data.code;
            modal.classList.add('open');
            backdrop.classList.add('open');
        }
        function closeModal() {
            modal.classList.remove('open');
            backdrop.classList.remove('open');
        }

        document.querySelectorAll('.enroll-btn').forEach(btn => {
            btn.addEventListener('click', () => openModal(btn.dataset));
        });
        closeBtn?.addEventListener('click', closeModal);
        backdrop?.addEventListener('click', closeModal);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        form?.addEventListener('submit', function (e) {
            const studentSelect = document.getElementById('modalStudentSelect');
            if (!studentSelect.value) {
                e.preventDefault();
                studentSelect.focus();
                return;
            }
            showSpinner(form.querySelector('button[type=submit]'));
        });
    }

    /* ---------- Add/Edit Course modal (courses.php) ---------- */
    const courseModal = document.getElementById('courseFormModal');
    if (courseModal) {
        const courseBackdrop = document.getElementById('courseFormBackdrop');
        const openBtn = document.getElementById('openAddCourse');
        const closeBtn = document.getElementById('closeCourseForm');

        openBtn?.addEventListener('click', () => { courseModal.classList.add('open'); courseBackdrop.classList.add('open'); });
        closeBtn?.addEventListener('click', () => { courseModal.classList.remove('open'); courseBackdrop.classList.remove('open'); });
        courseBackdrop?.addEventListener('click', () => { courseModal.classList.remove('open'); courseBackdrop.classList.remove('open'); });
    }

    

    /* ---------- Course search filter (courses.php) ---------- */
    document.getElementById('courseSearch')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.searchable').forEach(c => {
            c.style.display = c.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    /* ---------- Animate room-utilization progress bars in from 0 ---------- */
    const bars = document.querySelectorAll('.progress.wide span[data-width]');
    if (bars.length) {
        requestAnimationFrame(() => {
            setTimeout(() => {
                bars.forEach(b => { b.style.width = b.dataset.width + '%'; });
            }, 120);
        });
    }

    /* ---------- Count-up animation for dashboard stat numbers ---------- */
    document.querySelectorAll('.stat-card strong').forEach(el => {
        const target = parseInt(el.textContent, 10);
        if (isNaN(target)) return;
        const duration = 700;
        const start = performance.now();
        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased);
            if (progress < 1) requestAnimationFrame(tick);
            else el.textContent = target;
        }
        el.textContent = '0';
        requestAnimationFrame(tick);
    });

    /* ---------- Any other form submissions: confirm (if needed) + spinner feedback ---------- */
    document.querySelectorAll('form:not(#enrollForm)').forEach(f => {
        f.addEventListener('submit', function (e) {
            const msg = f.dataset.confirm;
            if (msg && !confirm(msg)) { e.preventDefault(); return; }
            showSpinner(f.querySelector('button[type=submit]'));
        });
    });

    /* ---------- Soft page-transition fade on internal navigation ---------- */
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a');
        if (!a) return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (a.target === '_blank' || a.hasAttribute('download')) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        document.body.classList.add('page-fade-out');
        setTimeout(() => { window.location.href = href; }, 170);
    });
});

function showSpinner(btn) {
    if (!btn || btn.dataset.loading) return;
    btn.dataset.loading = '1';
    btn.dataset.originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="btn-spinner"></span> Processing…';
    btn.disabled = true;
}
