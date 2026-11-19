<script>
(() => {
    if (window.__todoInit) return; window.__todoInit = true;
    const csrfName = <?= json_encode(csrf_token()) ?>;
    let csrf = <?= json_encode(csrf_hash()) ?>;
    document.addEventListener('change', async e => {
        const c = e.target;
        if (!c.classList.contains('todo-check')) return;
        const li = c.closest('li');
        li.classList.toggle('todo-done', c.checked);
        const body = new URLSearchParams({ [csrfName]: csrf, done: c.checked ? '1' : '0' });
        try {
            const r = await fetch(c.dataset.url, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json(); if (j.csrf) { csrf = j.csrf; document.querySelectorAll('input[name="' + csrfName + '"]').forEach(i => i.value = csrf); }
            if (c.checked && li.closest('.todo-hide-done')) setTimeout(() => { li.style.transition = 'opacity .3s'; li.style.opacity = 0; setTimeout(() => li.remove(), 300); }, 600);
        } catch (err) { c.checked = !c.checked; li.classList.toggle('todo-done', c.checked); alert('Opslaan mislukt, probeer opnieuw.'); }
    });
    document.addEventListener('click', e => {
        const b = e.target.closest('.todo-add [data-day]');
        if (!b) return;
        const d = new Date(); d.setDate(d.getDate() + parseInt(b.dataset.day, 10));
        b.closest('form').querySelector('.todo-date').value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    });
})();
</script>
