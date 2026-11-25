<?php
use App\Models\PurchaseInvoiceModel;

$typeList = [];
foreach ($types as $group => $list) {
    foreach ($list as $k => [$label, $icon, , $brand]) {
        $typeList[] = ['key' => $k, 'label' => $label, 'icon' => $icon, 'brand' => $brand, 'group' => $group];
    }
}
?>
<div class="scan-page">
<div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0"><i class="bi bi-upc-scan"></i> Doosscan</h3>
    <a href="<?= site_url('netwerk') ?>" class="small">Netwerk →</a>
</div>

<!-- Waar hoort het bij? -->
<div class="card mb-2 scan-ctx">
    <button type="button" class="scan-ctx-sum" data-bs-toggle="collapse" data-bs-target="#ctx-body" aria-expanded="true">
        <span id="ctx-summary" class="text-truncate">Kies klant en locatie</span>
        <i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse show" id="ctx-body"><div class="card-body pt-0">
        <div class="row g-2">
            <div class="col-12">
                <select id="ctx-quote" class="form-select">
                    <option value="">Uit offerte… (optioneel)</option>
                    <?php foreach ($quotes as $q) : ?><option value="<?= $q['id'] ?>" data-customer="<?= $q['customer_id'] ?>"><?= esc('#' . $q['id'] . ' · ' . $q['customer_name'] . ($q['title'] ? ' · ' . $q['title'] : '')) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <select id="ctx-customer" class="form-select">
                    <option value="">Klant…</option>
                    <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <select id="ctx-site" class="form-select" data-sites-for="#ctx-customer">
                    <option value="">Locatie (alle)</option>
                    <?php foreach ($sites as $s) : ?><option value="<?= $s['id'] ?>" data-customer="<?= $s['customer_id'] ?>"><?= esc($s['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-12">
                <div class="input-group">
                    <select id="ctx-invoice" class="form-select">
                        <option value="">Inkoopfactuur (optioneel)</option>
                        <?php foreach ($invoices as $inv) : ?><option value="<?= $inv['id'] ?>"><?= esc(PurchaseInvoiceModel::label($inv)) ?></option><?php endforeach ?>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#ctx-newinv" title="Nieuwe inkoopfactuur"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div class="collapse mt-2" id="ctx-newinv"><?= view('purchase/_quick', ['suppliers' => $suppliers, 'target' => '#ctx-invoice']) ?></div>
            </div>
        </div>
    </div></div>
</div>

<!-- Camera -->
<div class="scan-cam mb-2" id="cam">
    <video id="video" playsinline muted autoplay></video>
    <div class="scan-frame"><span></span></div>
    <div class="scan-cam-idle" id="cam-idle">
        <button type="button" class="btn btn-light btn-lg" id="cam-start"><i class="bi bi-camera"></i> Camera starten</button>
        <div class="small mt-2 text-white-50">Richt op de barcode bij S/N, MAC of ID</div>
    </div>
    <div class="scan-cam-tools">
        <button type="button" class="btn btn-sm btn-dark d-none" id="cam-torch" title="Lamp"><i class="bi bi-lightbulb"></i></button>
        <button type="button" class="btn btn-sm btn-dark d-none" id="cam-stop" title="Camera uit"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="scan-cam-msg d-none" id="cam-msg"></div>
</div>

<form id="manual" class="d-flex gap-2 mb-3" autocomplete="off">
    <input id="manual-code" class="form-control mono" placeholder="Of typ / plak een code" autocapitalize="characters" autocorrect="off" spellcheck="false">
    <button class="btn btn-outline-primary text-nowrap"><i class="bi bi-search"></i></button>
</form>

<!-- Resultaat -->
<div class="card mb-3 d-none" id="result"><div class="card-body" id="result-body"></div></div>

<div id="history-wrap" class="d-none">
    <div class="small text-muted mb-1">Deze sessie</div>
    <div class="net-list" id="history"></div>
</div>

<div class="scan-bar d-none" id="scan-bar">
    <button type="button" class="btn btn-outline-secondary" id="btn-skip">Overslaan</button>
    <button type="button" class="btn btn-primary flex-fill" id="btn-save"><i class="bi bi-check2"></i> Opslaan &amp; volgende</button>
</div>
</div>

<script>
(function () {
    const URLS = { lookup: <?= json_encode(site_url('scan/zoek')) ?>, save: <?= json_encode(site_url('scan/opslaan')) ?>, zxing: <?= json_encode(base_url('assets/zxing/zxing-browser.min.js')) ?> };
    const TYPES = <?= json_encode($typeList, JSON_UNESCAPED_UNICODE) ?>;
    const PRE = <?= json_encode($pre) ?>;
    const csrfName = document.querySelector('meta[name="csrf-name"]').content;
    const meta = document.querySelector('meta[name="csrf-hash"]');
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const ctx = { quote: $('ctx-quote'), customer: $('ctx-customer'), site: $('ctx-site'), invoice: $('ctx-invoice') };

    // ---------- Context (klant, locatie, offerte, factuur): onthouden op dit toestel ----------
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem('scanCtx') || '{}'); } catch (e) {}
    // Uit de link (?klant=, ?offerte=, ?factuur=) gaat voor; anders wat je de vorige keer koos
    const fromLink = PRE.customer || PRE.quote;
    const want = {
        quote: fromLink ? PRE.quote : saved.quote,
        customer: fromLink ? PRE.customer : saved.customer,
        site: fromLink ? PRE.site : saved.site,
        invoice: PRE.invoice ?? saved.invoice,
    };
    if (PRE.quote && !PRE.customer) {
        const o = [...ctx.quote.options].find(x => x.value === String(PRE.quote));
        if (o) want.customer = o.dataset.customer;
    }
    const setVal = (sel, v) => { if (v && [...sel.options].some(o => o.value === String(v))) sel.value = String(v); };
    setVal(ctx.quote, want.quote); setVal(ctx.customer, want.customer); ctx.customer.dispatchEvent(new Event('change')); setVal(ctx.site, want.site); setVal(ctx.invoice, want.invoice);

    function saveCtx() {
        try { localStorage.setItem('scanCtx', JSON.stringify({ quote: ctx.quote.value, customer: ctx.customer.value, site: ctx.site.value, invoice: ctx.invoice.value })); } catch (e) {}
        const txt = o => o.value ? o.options[o.selectedIndex].text : '';
        const parts = [txt(ctx.customer) || 'Geen klant', ctx.site.value ? txt(ctx.site) : '', ctx.invoice.value ? '🧾 ' + txt(ctx.invoice).split(' · ')[0] : ''].filter(Boolean);
        $('ctx-summary').textContent = parts.join(' · ');
    }
    ctx.quote.addEventListener('change', () => {
        const o = ctx.quote.options[ctx.quote.selectedIndex];
        if (o.dataset.customer) { ctx.customer.value = o.dataset.customer; ctx.customer.dispatchEvent(new Event('change')); }
        saveCtx(); relookup();
    });
    ctx.customer.addEventListener('change', () => {
        const so = ctx.site.options[ctx.site.selectedIndex];
        if (so && so.dataset.customer && so.dataset.customer !== ctx.customer.value) ctx.site.value = '';
        const o = ctx.quote.options[ctx.quote.selectedIndex];
        if (o && o.dataset.customer && o.dataset.customer !== ctx.customer.value) ctx.quote.value = '';
        saveCtx(); relookup();
    });
    ctx.site.addEventListener('change', () => { saveCtx(); relookup(); });
    ctx.invoice.addEventListener('change', saveCtx);
    saveCtx();
    // Is alles al gekozen? Dan het blok inklappen voor meer ruimte voor de camera
    if (ctx.customer.value && window.bootstrap) bootstrap.Collapse.getOrCreateInstance($('ctx-body'), { toggle: false }).hide();

    async function post(url, data) {
        const body = new FormData();
        body.append(csrfName, meta.content);
        Object.entries(data).forEach(([k, v]) => body.append(k, v ?? ''));
        const res = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json().catch(() => ({ error: 'Onverwacht antwoord van de server (' + res.status + ')' }));
        if (json.csrf) meta.content = json.csrf;
        if (!res.ok && !json.error) json.error = 'Fout ' + res.status;
        return json;
    }

    // ---------- Feedback ----------
    let audio;
    function beep(ok = true) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            const o = audio.createOscillator(), g = audio.createGain();
            o.frequency.value = ok ? 1250 : 330; g.gain.value = 0.08;
            o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + (ok ? 0.09 : 0.25));
        } catch (e) {}
        if (navigator.vibrate) navigator.vibrate(ok ? 60 : [80, 60, 80]);
    }

    // ---------- Camera ----------
    const video = $('video');
    let stream = null, detector = null, zx = null, zxControls = null, loopTimer = null, paused = false, lastCode = '', lastAt = 0;

    async function startCamera() {
        $('cam-msg').classList.add('d-none');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return camMsg('Deze browser geeft geen toegang tot de camera. Gebruik het invoerveld hieronder.');
        if (!window.isSecureContext) return camMsg('De camera werkt alleen via https.');
        try {
            if ('BarcodeDetector' in window) {
                const formats = await BarcodeDetector.getSupportedFormats();
                const wanted = ['code_128', 'code_39', 'code_93', 'data_matrix', 'qr_code', 'ean_13', 'ean_8', 'upc_a', 'itf', 'codabar', 'pdf417'].filter(f => formats.includes(f));
                if (wanted.includes('code_128')) detector = new BarcodeDetector({ formats: wanted });
            }
            stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } });
            video.srcObject = stream;
            await video.play();
            const track = stream.getVideoTracks()[0];
            try { await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }); } catch (e) {}
            const caps = track.getCapabilities ? track.getCapabilities() : {};
            $('cam-torch').classList.toggle('d-none', !caps.torch);
            $('cam-idle').classList.add('d-none'); $('cam-stop').classList.remove('d-none');
            $('cam').classList.add('live');
            if (detector) {
                loopTimer = setInterval(async () => {
                    if (paused || video.readyState < 2) return;
                    try { const codes = await detector.detect(video); if (codes.length) onCode(pick(codes.map(c => c.rawValue))); } catch (e) {}
                }, 180);
            } else {
                await loadZxing();
                zx = new ZXingBrowser.BrowserMultiFormatReader(undefined, { delayBetweenScanAttempts: 150, delayBetweenScanSuccess: 400 });
                zxControls = await zx.decodeFromVideoElement(video, (result) => { if (result && !paused) onCode(result.getText()); });
            }
        } catch (e) {
            stopCamera();
            camMsg(e && e.name === 'NotAllowedError' ? 'Geen toestemming voor de camera. Sta de camera toe in de instellingen van je browser.' : 'Camera starten lukte niet: ' + (e.message || e));
        }
    }
    function pick(values) {
        // Meerdere barcodes in beeld: liefst de MAC/het serienummer, niet de EAN-productcode
        const nonEan = values.filter(v => !/^\d{8}$|^\d{12,14}$/.test(v));
        return (nonEan.length ? nonEan : values).sort((a, b) => b.length - a.length)[0];
    }
    function loadZxing() {
        return new Promise((ok, fail) => {
            if (window.ZXingBrowser) return ok();
            const s = document.createElement('script'); s.src = URLS.zxing; s.onload = ok; s.onerror = () => fail(new Error('scanner laden mislukt'));
            document.head.appendChild(s);
        });
    }
    function stopCamera() {
        clearInterval(loopTimer); loopTimer = null;
        if (zxControls) { try { zxControls.stop(); } catch (e) {} zxControls = null; }
        if (stream) stream.getTracks().forEach(t => t.stop());
        stream = null; video.srcObject = null;
        $('cam-idle').classList.remove('d-none'); $('cam-stop').classList.add('d-none'); $('cam-torch').classList.add('d-none');
        $('cam').classList.remove('live');
    }
    function camMsg(t) { const m = $('cam-msg'); m.textContent = t; m.classList.remove('d-none'); }
    $('cam-start').addEventListener('click', startCamera);
    $('cam-stop').addEventListener('click', stopCamera);
    let torch = false;
    $('cam-torch').addEventListener('click', async () => {
        torch = !torch;
        try { await stream.getVideoTracks()[0].applyConstraints({ advanced: [{ torch }] }); $('cam-torch').classList.toggle('btn-warning', torch); } catch (e) {}
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden && stream) stopCamera(); });

    $('manual').addEventListener('submit', e => { e.preventDefault(); const v = $('manual-code').value.trim(); if (v) { lastCode = ''; onCode(v); } });

    // ---------- Code verwerken ----------
    let current = null; // { code, data }
    function onCode(code) {
        const now = Date.now();
        if (code === lastCode && now - lastAt < 4000) return;
        if (current && current.code === code) return;
        lastCode = code; lastAt = now;
        paused = true;
        beep(true);
        lookup(code);
    }
    function relookup() { if (current) lookup(current.code); }

    async function lookup(code) {
        $('result').classList.remove('d-none');
        $('result-body').innerHTML = '<div class="text-muted"><span class="spinner-border spinner-border-sm"></span> Zoeken…</div>';
        const data = await post(URLS.lookup, { code, customer_id: ctx.customer.value, site_id: ctx.site.value, quote_id: ctx.quote.value });
        if (data.error) { render(code, null, data.error); return; }
        // Klant afgeleid van de UniFi/UISP-site?
        if (!ctx.customer.value && data.customer_id) {
            ctx.customer.value = String(data.customer_id); ctx.customer.dispatchEvent(new Event('change'));
            if (data.site_id) ctx.site.value = String(data.site_id);
            saveCtx();
        }
        current = { code, data };
        render(code, data);
        $('result').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function render(code, d, error) {
        $('scan-bar').classList.toggle('d-none', !d || !ctx.customer.value);
        if (!d) { $('result-body').innerHTML = '<div class="text-danger">' + esc(error) + '</div><div class="mono small mt-1">' + esc(code) + '</div>'; resumeSoon(); return; }
        const p = d.parsed;
        let h = '<div class="d-flex justify-content-between align-items-start gap-2"><div class="mono small text-break scan-raw">' + esc(p.raw) + '</div>'
              + '<button type="button" class="btn-close" aria-label="Sluiten" id="res-close"></button></div>';

        if (p.ean && !p.mac && !p.serial && !p.device_id) {
            h += '<div class="alert alert-warning py-2 small mt-2 mb-0"><i class="bi bi-upc"></i> Dit is de productcode (EAN), geen serienummer. Scan de barcode bij <strong>S/N</strong>, <strong>MAC</strong> of <strong>ID</strong>.</div>';
            $('result-body').innerHTML = h; $('scan-bar').classList.add('d-none'); bindClose(); beep(false); resumeSoon(1200); return;
        }

        if (d.existing.length) {
            h += '<div class="alert alert-success py-2 small mt-2 mb-2"><i class="bi bi-check-circle"></i> Staat al in de kluis: '
               + d.existing.map(e => '<a href="' + e.url + '" class="alert-link">' + esc(e.name) + '</a> <span class="text-muted">(' + esc([e.customer, e.site].filter(Boolean).join(' · ')) + ')</span>').join(', ') + '</div>';
        }
        if (d.network) {
            const n = d.network, st = n.status === 'online' ? '<span class="dot dot-on"></span> online' : (n.status === 'offline' ? '<span class="dot dot-off"></span> offline' : '<span class="dot dot-unk"></span>');
            h += '<div class="alert alert-info py-2 small mt-2 mb-2"><i class="bi bi-wifi"></i> In ' + esc(n.platform) + ': <strong>' + esc(n.name || n.model) + '</strong> ' + esc(n.model && n.name ? '· ' + n.model : '') + ' · ' + esc(n.site || '') + ' · ' + st + '</div>';
        }

        // Velden (aanpasbaar)
        const serial = p.serial || p.device_id || '';
        h += '<div class="row g-2 mt-1">'
           + '<div class="col-7"><label class="form-label small mb-0">' + (p.device_id && !p.serial ? 'Device-ID' : 'Serienummer') + '</label><input class="form-control mono" id="f-serial" value="' + esc(serial) + '" autocapitalize="characters"></div>'
           + '<div class="col-5"><label class="form-label small mb-0">Merk</label><input class="form-control" id="f-brand" value="' + esc(p.brand || '') + '" list="brand-list"></div>'
           + '<div class="col-7"><label class="form-label small mb-0">MAC-adres</label><input class="form-control mono" id="f-mac" value="' + esc(d.mac || '') + '" autocapitalize="characters"></div>'
           + '<div class="col-5"><label class="form-label small mb-0">Model</label><input class="form-control" id="f-model" value="' + esc(p.model || '') + '"></div>'
           + '</div>';
        if (p.verification) {
            h += '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="f-verif" checked><label class="form-check-label small" for="f-verif">Verificatiecode <span class="mono">' + esc(p.verification) + '</span> versleuteld opslaan</label></div>';
        }

        if (!ctx.customer.value) {
            h += '<div class="alert alert-warning py-2 small mt-3 mb-0"><i class="bi bi-person"></i> Kies bovenaan een klant om dit aan een apparaat te koppelen.</div>';
            $('result-body').innerHTML = h; bindClose();
            bootstrap.Collapse.getOrCreateInstance($('ctx-body'), { toggle: false }).show();
            return;
        }

        // Bij welk apparaat?
        const existingHere = d.existing.find(e => d.candidates.some(c => c.id === e.id));
        let pre = existingHere ? existingHere.id : null;
        if (pre === null && d.candidates.length && d.candidates[0].score >= 5) pre = d.candidates[0].id;
        h += '<div class="small fw-semibold mt-3 mb-1">Hoort bij</div><div class="scan-cands" id="cands">';
        d.candidates.forEach((c, i) => {
            h += '<label class="scan-cand' + (i >= 6 && c.id !== pre ? ' more d-none' : '') + '"><input type="radio" name="target" value="' + c.id + '"' + (c.id === pre ? ' checked' : '') + '>'
               + '<i class="bi bi-' + esc(c.icon) + '"></i><span class="min-w-0"><span class="d-block text-truncate fw-semibold">' + esc(c.name) + '</span>'
               + '<span class="d-block small text-muted text-truncate">' + esc([c.model, c.site, c.room].filter(Boolean).join(' · ')) + '</span>'
               + (c.serial ? '<span class="d-block small text-warning-emphasis text-truncate">heeft al: <span class="mono">' + esc(c.serial) + '</span></span>' : '') + '</span></label>';
        });
        if (d.candidates.length > 6) h += '<button type="button" class="btn btn-link btn-sm p-0" id="more">Meer apparaten (' + (d.candidates.length - 6) + ')</button>';
        h += '<label class="scan-cand"><input type="radio" name="target" value="new"' + (pre === null ? ' checked' : '') + '><i class="bi bi-plus-lg"></i><span class="fw-semibold">Nieuw apparaat</span></label>';
        h += '</div>';

        // Nieuw: soort kiezen (merk-relevante soorten eerst)
        const brand = (p.brand || '').toLowerCase();
        const order = t => (brand && (t.brand || '').toLowerCase() === brand ? 0 : 1);
        const types = TYPES.filter(t => !['wifi', 'account'].includes(t.key)).sort((a, b) => order(a) - order(b));
        h += '<div id="new-box" class="' + (pre === null ? '' : 'd-none') + ' mt-2"><div class="type-grid scan-types">'
           + types.map(t => '<button type="button" class="type-btn' + (t.key === d.type_guess ? ' active' : '') + '" data-type="' + t.key + '"><i class="bi bi-' + esc(t.icon) + '"></i><span>' + esc(t.label) + '</span></button>').join('')
           + '</div><div class="row g-2 mt-1"><div class="col-7"><input class="form-control" id="f-name" placeholder="Naam (leeg = automatisch)"></div><div class="col-5"><input class="form-control" id="f-room" placeholder="Ruimte"></div></div></div>';

        $('result-body').innerHTML = h;
        bindClose();
        const more = $('more'); if (more) more.addEventListener('click', () => { document.querySelectorAll('.scan-cand.more').forEach(e => e.classList.remove('d-none')); more.remove(); });
        document.querySelectorAll('input[name="target"]').forEach(r => r.addEventListener('change', () => $('new-box').classList.toggle('d-none', r.value !== 'new' || !r.checked)));
        document.querySelectorAll('.scan-types .type-btn').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('.scan-types .type-btn').forEach(x => x.classList.toggle('active', x === b));
            const t = TYPES.find(x => x.key === b.dataset.type);
            if (t && t.brand && !$('f-brand').value) $('f-brand').value = t.brand;
        }));
    }
    function bindClose() { const c = $('res-close'); if (c) c.addEventListener('click', reset); }

    function reset() {
        current = null;
        $('result').classList.add('d-none'); $('scan-bar').classList.add('d-none');
        $('manual-code').value = '';
        paused = false;
    }
    function resumeSoon(ms = 1500) { setTimeout(() => { if (!current) paused = false; }, ms); }

    $('btn-skip').addEventListener('click', reset);
    $('btn-save').addEventListener('click', async () => {
        if (!current) return;
        const target = (document.querySelector('input[name="target"]:checked') || {}).value || 'new';
        const type = (document.querySelector('.scan-types .type-btn.active') || {}).dataset?.type || '';
        if (target === 'new' && !type) { alert('Kies welk soort apparaat het is.'); return; }
        const btn = $('btn-save'); btn.disabled = true;
        const v = id => ($(id) ? $(id).value.trim() : '');
        const res = await post(URLS.save, {
            device_id: target, type, name: v('f-name'), room: v('f-room'),
            customer_id: ctx.customer.value, site_id: ctx.site.value, purchase_invoice_id: ctx.invoice.value,
            serial: v('f-serial'), mac: v('f-mac'), model: v('f-model'), brand: v('f-brand'),
            verification: $('f-verif') && $('f-verif').checked ? current.data.parsed.verification : '',
            network_device_id: current.data.network && !current.data.network.device_id ? current.data.network.id : '',
        });
        btn.disabled = false;
        if (res.error) { beep(false); alert(res.error); return; }
        beep(true);
        const code = v('f-serial') || v('f-mac') || current.code;
        const item = document.createElement('a');
        item.className = 'net-item d-flex align-items-center gap-2 text-reset text-decoration-none';
        item.href = res.device.url;
        item.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i><span class="min-w-0 flex-fill"><span class="d-block fw-semibold text-truncate">' + esc(res.device.name) + '</span><span class="d-block small mono text-muted text-truncate">' + esc(code) + '</span></span><i class="bi bi-' + esc(res.device.icon) + ' text-muted"></i>';
        $('history').prepend(item); $('history-wrap').classList.remove('d-none');
        lastCode = current.code; lastAt = Date.now();
        reset();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>
<datalist id="brand-list"><option>Ubiquiti</option><option>Hikvision</option><option>Ajax</option><option>HiLook</option><option>Dahua</option><option>Synology</option></datalist>
