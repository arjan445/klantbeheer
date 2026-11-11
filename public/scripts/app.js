// Klantbeheer - kleine hulpjes
(function () {
    const csrfName = document.querySelector('meta[name="csrf-name"]')?.content;
    let csrfHash = document.querySelector('meta[name="csrf-hash"]')?.content;

    // DataTables op tabellen met class .datatable
    if (window.jQuery && jQuery.fn.dataTable) {
        jQuery('table.datatable').each(function () {
            jQuery(this).DataTable({
                pageLength: 50,
                order: [],
                language: {
                    search: 'Zoeken:', lengthMenu: '_MENU_ per pagina', info: '_START_ tot _END_ van _TOTAL_',
                    infoEmpty: 'Geen resultaten', infoFiltered: '(gefilterd uit _MAX_)', zeroRecords: 'Niets gevonden',
                    emptyTable: 'Geen gegevens', paginate: { first: 'Eerste', last: 'Laatste', next: 'Volgende', previous: 'Vorige' }
                }
            });
        });
    }

    // Wachtwoord tonen: <button data-reveal="/apparaten/5/geheim" data-field="password" data-target="#pw5">
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-reveal]');
        if (!btn) return;
        e.preventDefault();
        const target = document.querySelector(btn.dataset.target);
        if (btn.dataset.shown === '1') {
            target.textContent = '••••••••';
            btn.dataset.shown = '0';
            btn.innerHTML = '<i class="bi bi-eye"></i>';
            return;
        }
        const body = new FormData();
        body.append(csrfName, csrfHash);
        body.append('field', btn.dataset.field || 'password');
        const res = await fetch(btn.dataset.reveal, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json().catch(() => ({}));
        if (data.csrf) csrfHash = data.csrf;
        if (!res.ok) { alert(data.error || 'Kon het wachtwoord niet ophalen.'); return; }
        target.textContent = data.value === '' ? '(leeg)' : data.value;
        btn.dataset.shown = '1';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
        // Na 30 seconden weer verbergen
        setTimeout(() => { if (btn.dataset.shown === '1') btn.click(); }, 30000);
    });

    // Kopiëren: haalt eerst op (en logt) en zet het op het klembord
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        e.preventDefault();
        let value = btn.dataset.copyValue;
        if (value === undefined) {
            const body = new FormData();
            body.append(csrfName, csrfHash);
            body.append('field', btn.dataset.field || 'password');
            const res = await fetch(btn.dataset.copy, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => ({}));
            if (data.csrf) csrfHash = data.csrf;
            if (!res.ok) { alert(data.error || 'Kon het wachtwoord niet ophalen.'); return; }
            value = data.value;
        }
        await navigator.clipboard.writeText(value);
        const old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i>';
        setTimeout(() => btn.innerHTML = old, 1500);
    });

    // Wachtwoord genereren in formulieren: <button data-generate="#password">
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-generate]');
        if (!btn) return;
        e.preventDefault();
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%*+-=?';
        const arr = new Uint32Array(16);
        crypto.getRandomValues(arr);
        const input = document.querySelector(btn.dataset.generate);
        input.value = Array.from(arr, n => chars[n % chars.length]).join('');
        input.type = 'text';
    });

    // Locaties filteren op gekozen klant (select[data-sites-for])
    document.querySelectorAll('select[data-sites-for]').forEach(function (siteSelect) {
        const customerSelect = document.querySelector(siteSelect.dataset.sitesFor);
        if (!customerSelect) return;
        const filter = () => {
            const cid = customerSelect.value;
            siteSelect.querySelectorAll('option[data-customer]').forEach(o => {
                const show = o.dataset.customer === cid;
                o.hidden = !show;
                if (!show && o.selected) siteSelect.value = '';
            });
        };
        customerSelect.addEventListener('change', filter);
        filter();
    });

    // Selecteer alles in facturatie
    document.querySelectorAll('[data-check-all]').forEach(function (box) {
        box.addEventListener('change', function () {
            document.querySelectorAll(box.dataset.checkAll).forEach(c => c.checked = box.checked);
        });
    });
})();
