<?php
use App\Libraries\Network;

$typeIcons = [];
foreach (device_types() as $k => $t) {
    $typeIcons[$k] = $t[1];
}
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div class="min-w-0">
        <a href="<?= site_url('netwerk?tab=sites') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Netwerk</a>
        <h3 class="mb-0 text-truncate"><i class="bi bi-diagram-3"></i> <?= esc($site['name']) ?></h3>
        <div class="small text-muted"><?= esc(Network::platformLabel($site['platform'])) ?><?= $loc ? ' · <a href="' . site_url('klanten/' . $loc['customer_id']) . '">' . esc($loc['customer_name']) . '</a> · ' . esc($loc['name']) : '' ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select class="form-select form-select-sm" style="max-width: 220px" onchange="if (this.value) location.href = this.value">
            <option value="">Andere site…</option>
            <?php foreach ($others as $o) : ?><option value="<?= site_url('netwerk/topologie/' . $o['id']) ?>" <?= (int) $o['id'] === (int) $site['id'] ? 'selected' : '' ?>><?= esc(Network::platformLabel($o['platform']) . ' · ' . $o['name']) ?></option><?php endforeach ?>
        </select>
        <?php if ($manage && $site['platform'] === 'unifi') : ?>
            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="topo-refresh"><i class="bi bi-arrow-repeat"></i> Verbindingen ophalen</button>
        <?php endif ?>
    </div>
</div>

<div class="topo-wrap" id="topo-wrap">
    <svg id="topo" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Topologie"></svg>
    <div class="topo-tools">
        <button type="button" class="btn btn-light btn-sm" data-zoom="1.25" title="Inzoomen"><i class="bi bi-plus-lg"></i></button>
        <button type="button" class="btn btn-light btn-sm" data-zoom="0.8" title="Uitzoomen"><i class="bi bi-dash-lg"></i></button>
        <button type="button" class="btn btn-light btn-sm" id="topo-fit" title="Passend maken"><i class="bi bi-arrows-fullscreen"></i></button>
    </div>
    <div class="topo-empty d-none" id="topo-empty">Nog geen apparaten in deze site.</div>
</div>

<div class="topo-legend small text-muted mt-2">
    <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="e-wired"/></svg> kabel</span>
    <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="e-wireless"/></svg> wifi</span>
    <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="e-ptp"/></svg> straalverbinding</span>
    <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="e-vpn"/></svg> VPN</span>
    <span><i class="bi bi-lock-fill"></i> zelf ingesteld</span>
    <?php if ($lastAt) : ?><span class="ms-auto">verbindingen opgehaald <?= esc(Network::ago(date('Y-m-d H:i:s', $lastAt))) ?> geleden</span><?php endif ?>
</div>

<?php if ($manage) : ?>
<div class="card mt-3"><div class="card-body py-2">
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <span class="small fw-semibold text-nowrap"><i class="bi bi-plus-circle"></i> Apparaat uit andere site toevoegen</span>
        <select class="form-select form-select-sm flex-fill" id="add-select" style="min-width: 200px">
            <option value="">Bijv. een UISP-straalverbinding…</option>
            <?php foreach ($addable as $group => $list) : ?>
                <optgroup label="<?= esc($group, 'attr') ?>">
                    <?php foreach ($list as $a) : ?><option value="<?= $a['id'] ?>"><?= esc($a['name']) ?></option><?php endforeach ?>
                </optgroup>
            <?php endforeach ?>
        </select>
        <button type="button" class="btn btn-sm btn-primary" id="add-btn">Toevoegen</button>
    </div>
</div></div>
<?php endif ?>

<!-- Verbinding bewerken -->
<div class="offcanvas offcanvas-bottom topo-sheet" tabindex="-1" id="edit-sheet">
    <div class="offcanvas-header pb-1">
        <div class="min-w-0">
            <h5 class="offcanvas-title text-truncate" id="ed-title"></h5>
            <div class="small text-muted text-truncate" id="ed-sub"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body pt-1">
        <div class="alert alert-info py-2 small d-none" id="ed-auto"></div>
        <?php if ($manage) : ?>
        <form id="ed-form" autocomplete="off">
            <input type="hidden" name="child_id" id="ed-child">
            <label class="form-label small mb-1">Hangt aan</label>
            <select class="form-select" name="parent_id" id="ed-parent"></select>
            <div class="row g-2 mt-1">
                <div class="col-6"><label class="form-label small mb-1">Poort daar</label><input class="form-control" name="parent_port" id="ed-port" inputmode="numeric" placeholder="bijv. 8"></div>
                <div class="col-6"><label class="form-label small mb-1">Eigen poort</label><input class="form-control" name="local_port" id="ed-local" inputmode="numeric" placeholder="optioneel"></div>
            </div>
            <label class="form-label small mb-1 mt-2">Soort verbinding</label>
            <div class="topo-types" id="ed-types">
                <?php foreach ($types as $k => $label) : ?>
                    <label><input type="radio" name="link_type" value="<?= $k ?>"> <span><?= esc($label) ?></span></label>
                <?php endforeach ?>
            </div>
            <label class="form-label small mb-1 mt-2">Notitie</label>
            <input class="form-control" name="note" id="ed-note" placeholder="bijv. kabel via kruipruimte, PTP naar schuur">
            <div class="small text-muted mt-2"><i class="bi bi-lock"></i> Wat je hier opslaat, staat vast: de synchronisatie verandert het niet meer.</div>
            <div class="d-flex gap-2 mt-3 flex-wrap">
                <button class="btn btn-primary flex-fill"><i class="bi bi-check2"></i> Opslaan</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="ed-unlock"><i class="bi bi-unlock"></i> Terug naar automatisch</button>
            </div>
            <div class="small text-danger mt-2" id="ed-err"></div>
        </form>
        <?php endif ?>
        <div class="mt-3 small"><a href="#" id="ed-device" class="d-none"><i class="bi bi-box-arrow-up-right"></i> Apparaat in de kluis openen</a></div>
    </div>
</div>

<script>
(function () {
    const SITE = <?= (int) $site['id'] ?>;
    const URL = <?= json_encode(site_url('netwerk/topologie/' . $site['id'])) ?>;
    const DEVICE_URL = <?= json_encode(site_url('apparaten/')) ?>;
    const ICONS = <?= json_encode($typeIcons) ?>;
    const TYPES = <?= json_encode($types, JSON_UNESCAPED_UNICODE) ?>;
    const MANAGE = <?= $manage ? 'true' : 'false' ?>;
    let nodes = <?= json_encode($nodes, JSON_UNESCAPED_UNICODE) ?>;

    const meta = document.querySelector('meta[name="csrf-hash"]');
    const csrfName = document.querySelector('meta[name="csrf-name"]').content;
    const svg = document.getElementById('topo');
    const NS = 'http://www.w3.org/2000/svg';
    const W = 176, H = 62, GX = 22, GY = 96;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const cut = (s, n) => { s = String(s ?? ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; };

    async function post(path, data) {
        const body = new FormData();
        body.append(csrfName, meta.content);
        Object.entries(data).forEach(([k, v]) => body.append(k, v ?? ''));
        const res = await fetch(URL + path, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json().catch(() => ({ error: 'Onverwacht antwoord (' + res.status + ')' }));
        if (json.csrf) meta.content = json.csrf;
        if (!res.ok && !json.error) json.error = 'Fout ' + res.status;
        return json;
    }

    // ---------- Layout: boom van boven naar beneden ----------
    let pos = {}, bounds = { w: 0, h: 0 };
    function layout() {
        const byId = Object.fromEntries(nodes.map(n => [n.id, n]));
        const kids = {};
        nodes.forEach(n => { if (n.parent && byId[n.parent]) (kids[n.parent] ||= []).push(n); });
        const portNum = p => { const m = String(p ?? '').match(/\d+/); return m ? +m[0] : 9999; };
        Object.values(kids).forEach(list => list.sort((a, b) => portNum(a.parent_port) - portNum(b.parent_port) || a.name.localeCompare(b.name)));
        const rank = n => (n.type === 'router' ? 0 : n.type === 'switch' ? 1 : 2);
        const roots = nodes.filter(n => !n.parent || !byId[n.parent]).sort((a, b) => rank(a) - rank(b) || (kids[b.id]?.length || 0) - (kids[a.id]?.length || 0) || a.name.localeCompare(b.name));
        pos = {};
        let x = 0, maxDepth = 0;
        const place = (n, depth, guard) => {
            if (guard.has(n.id)) return;
            guard.add(n.id);
            maxDepth = Math.max(maxDepth, depth);
            const ch = kids[n.id] || [];
            if (!ch.length) { pos[n.id] = { x, y: depth * (H + GY) }; x += W + GX; return; }
            ch.forEach(c => place(c, depth + 1, guard));
            const xs = ch.map(c => pos[c.id]?.x).filter(v => v !== undefined);
            pos[n.id] = { x: (Math.min(...xs) + Math.max(...xs)) / 2, y: depth * (H + GY) };
        };
        const guard = new Set();
        roots.forEach(r => { place(r, 0, guard); x += GX * 2; });
        bounds = { w: Math.max(x - GX * 3, W), h: (maxDepth + 1) * (H + GY) - GY };
    }

    // ---------- Tekenen ----------
    function el(tag, attrs, parent) {
        const e = document.createElementNS(NS, tag);
        Object.entries(attrs || {}).forEach(([k, v]) => e.setAttribute(k, v));
        if (parent) parent.appendChild(e);
        return e;
    }
    function draw() {
        layout();
        svg.innerHTML = '';
        document.getElementById('topo-empty').classList.toggle('d-none', nodes.length > 0);
        const g = el('g', { id: 'topo-g' }, svg);
        const edges = el('g', {}, g), labels = el('g', {}, g), boxes = el('g', {}, g);
        nodes.forEach(n => {
            if (!n.parent || !pos[n.parent] || !pos[n.id]) return;
            const p = pos[n.parent], c = pos[n.id];
            const x1 = p.x + W / 2, y1 = p.y + H, x2 = c.x + W / 2, y2 = c.y, my = (y1 + y2) / 2;
            el('path', { d: `M${x1},${y1} C${x1},${my} ${x2},${my} ${x2},${y2}`, class: 'edge e-' + (n.link || 'wired') + (n.locked ? ' locked' : '') }, edges);
            const txt = [n.parent_port ? 'poort ' + n.parent_port : (n.local_port ? 'eigen poort ' + n.local_port : ''), n.link && n.link !== 'wired' ? (n.link === 'ptp' ? 'PTP' : n.link === 'vpn' ? 'VPN' : 'wifi') : ''].filter(Boolean).join(' · ');
            if (txt || n.locked) {
                const t = el('text', { x: x2, y: y2 - 12, class: 'elabel', 'text-anchor': 'middle' }, labels);
                t.textContent = (n.locked ? '🔒 ' : '') + txt;
            }
        });
        nodes.forEach(n => {
            const p = pos[n.id]; if (!p) return;
            const b = el('g', { class: 'node st-' + n.status + (n.monitor ? '' : ' nomon') + (n.foreign ? ' foreign' : ''), transform: `translate(${p.x},${p.y})`, tabindex: 0, 'data-id': n.id }, boxes);
            el('rect', { width: W, height: H, rx: 10 }, b);
            el('circle', { cx: W - 14, cy: 14, r: 5, class: 'sdot' }, b);
            const fo = el('foreignObject', { x: 8, y: 8, width: 30, height: 30 }, b);
            fo.innerHTML = '<div xmlns="http://www.w3.org/1999/xhtml" class="nicon"><i class="bi bi-' + esc(ICONS[n.type] || 'hdd-network') + '"></i></div>';
            const t1 = el('text', { x: 44, y: 24, class: 'nname' }, b); t1.textContent = cut(n.name, 17);
            const t2 = el('text', { x: 44, y: 41, class: 'nsub' }, b); t2.textContent = cut(n.model || '', 20);
            const t3 = el('text', { x: 44, y: 55, class: 'nsub mono' }, b); t3.textContent = cut(n.ip || n.mac || '', 22);
            if (n.foreign) { const t4 = el('text', { x: W - 8, y: H - 6, class: 'nplat', 'text-anchor': 'end' }, b); t4.textContent = n.platform.toUpperCase(); }
            const tt = el('title', {}, b); tt.textContent = n.name + (n.status === 'offline' ? ' (offline)' : '');
            b.addEventListener('click', () => { if (!moved) openEditor(n.id); });
            b.addEventListener('keydown', e => { if (e.key === 'Enter') openEditor(n.id); });
        });
        fit();
    }

    // ---------- Pan & zoom ----------
    let view = { x: 0, y: 0, w: 100, h: 100 }, moved = false;
    function apply() { svg.setAttribute('viewBox', `${view.x} ${view.y} ${view.w} ${view.h}`); }
    function fit() {
        const r = svg.getBoundingClientRect(), pad = 30;
        const w = bounds.w + pad * 2, h = bounds.h + pad * 2;
        const ratio = r.width / Math.max(r.height, 1);
        // Niet kleiner dan leesbaar: liever scrollen/slepen dan piepkleine tekst
        const minScale = 0.55, scale = Math.max(minScale, Math.min(r.width / w, r.height / h, 1.2));
        view.w = r.width / scale; view.h = r.height / scale;
        view.x = -pad + (w - view.w) / 2; view.y = -pad;
        if (view.w < w && scale === minScale) view.x = -pad; // te breed: links beginnen
        apply();
    }
    function zoom(f, cx, cy) {
        const r = svg.getBoundingClientRect();
        cx ??= r.left + r.width / 2; cy ??= r.top + r.height / 2;
        const px = view.x + (cx - r.left) / r.width * view.w, py = view.y + (cy - r.top) / r.height * view.h;
        const nw = Math.min(Math.max(view.w / f, 200), 20000), k = nw / view.w;
        view.x = px - (px - view.x) * k; view.y = py - (py - view.y) * k; view.w = nw; view.h *= k;
        apply();
    }
    const pts = new Map(); let last = null, pinch = null;
    svg.addEventListener('pointerdown', e => { pts.set(e.pointerId, e); moved = false; last = { x: e.clientX, y: e.clientY }; });
    window.addEventListener('pointermove', e => {
        if (!pts.has(e.pointerId)) return;
        pts.set(e.pointerId, e);
        const r = svg.getBoundingClientRect();
        if (pts.size === 2) {
            const [a, b] = [...pts.values()], d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
            if (pinch) zoom(d / pinch, (a.clientX + b.clientX) / 2, (a.clientY + b.clientY) / 2);
            pinch = d; moved = true; return;
        }
        const dx = e.clientX - last.x, dy = e.clientY - last.y;
        if (Math.abs(dx) + Math.abs(dy) > 4) moved = true;
        if (moved) { view.x -= dx * view.w / r.width; view.y -= dy * view.h / r.height; apply(); last = { x: e.clientX, y: e.clientY }; }
    });
    const up = e => { pts.delete(e.pointerId); if (pts.size < 2) pinch = null; setTimeout(() => { if (!pts.size) moved = false; }, 0); };
    window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
    svg.addEventListener('wheel', e => { e.preventDefault(); zoom(e.deltaY < 0 ? 1.15 : 0.87, e.clientX, e.clientY); }, { passive: false });
    document.querySelectorAll('[data-zoom]').forEach(b => b.addEventListener('click', () => zoom(+b.dataset.zoom)));
    document.getElementById('topo-fit').addEventListener('click', fit);
    window.addEventListener('resize', () => fit());

    // ---------- Bewerken ----------
    const sheet = document.getElementById('edit-sheet');
    const $ = id => document.getElementById(id);
    function descendants(id) {
        const out = new Set([id]); let grew = true;
        while (grew) { grew = false; nodes.forEach(n => { if (n.parent && out.has(n.parent) && !out.has(n.id)) { out.add(n.id); grew = true; } }); }
        return out;
    }
    function openEditor(id) {
        const n = nodes.find(x => x.id === id); if (!n) return;
        $('ed-title').textContent = n.name;
        $('ed-sub').textContent = [n.model, n.ip, n.mac, n.status === 'offline' ? 'offline' : ''].filter(Boolean).join(' · ');
        const a = n.auto;
        const autoTxt = a ? (a.parent ? (a.parent_name || 'onbekend apparaat') + (a.port ? ', poort ' + a.port : '') + (a.type && a.type !== 'wired' ? ' (' + (TYPES[a.type] || a.type).toLowerCase() + ')' : '') : 'geen bovenliggend apparaat') : '';
        $('ed-auto').classList.toggle('d-none', !a);
        $('ed-auto').innerHTML = a ? '<i class="bi bi-wifi"></i> ' + esc(n.platform === 'uisp' ? 'UISP' : 'UniFi') + ' ziet: <strong>' + esc(autoTxt) + '</strong>' : '';
        const dev = $('ed-device');
        dev.classList.toggle('d-none', !n.device_id); if (n.device_id) dev.href = DEVICE_URL + n.device_id;
        if (!MANAGE) { bootstrap.Offcanvas.getOrCreateInstance(sheet).show(); return; }
        $('ed-child').value = n.id;
        const bad = descendants(n.id);
        const opts = nodes.filter(x => !bad.has(x.id)).sort((x, y) => x.name.localeCompare(y.name));
        $('ed-parent').innerHTML = '<option value="">— nergens (bovenaan) —</option>' + opts.map(x => '<option value="' + x.id + '"' + (x.id === n.parent ? ' selected' : '') + '>' + esc(x.name + (x.model ? ' · ' + x.model : '')) + '</option>').join('');
        $('ed-port').value = n.parent_port || '';
        $('ed-local').value = n.local_port || '';
        $('ed-note').value = n.note || '';
        const t = n.link || (n.platform === 'uisp' ? 'ptp' : 'wired');
        document.querySelectorAll('#ed-types input').forEach(r => r.checked = r.value === t);
        $('ed-unlock').classList.toggle('d-none', !n.locked);
        $('ed-err').textContent = '';
        bootstrap.Offcanvas.getOrCreateInstance(sheet).show();
    }
    if (MANAGE) {
        $('ed-form').addEventListener('submit', async e => {
            e.preventDefault();
            const f = new FormData(e.target), data = Object.fromEntries(f.entries());
            const res = await post('/verbinding', data);
            if (res.error) { $('ed-err').textContent = res.error; return; }
            nodes = res.nodes; draw(); bootstrap.Offcanvas.getOrCreateInstance(sheet).hide();
        });
        $('ed-unlock').addEventListener('click', async () => {
            const res = await post('/automatisch', { child_id: $('ed-child').value });
            if (res.error) { $('ed-err').textContent = res.error; return; }
            nodes = res.nodes; draw(); bootstrap.Offcanvas.getOrCreateInstance(sheet).hide();
        });
        $('add-btn').addEventListener('click', async () => {
            const id = $('add-select').value; if (!id) return;
            const res = await post('/toevoegen', { child_id: id });
            if (res.error) { alert(res.error); return; }
            nodes = res.nodes; draw();
            $('add-select').querySelector('option[value="' + id + '"]')?.remove();
            openEditor(+id);
        });
        const rb = $('topo-refresh');
        if (rb) rb.addEventListener('click', async () => {
            rb.disabled = true; const old = rb.innerHTML; rb.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Bezig…';
            const res = await post('/ophalen', {});
            rb.disabled = false; rb.innerHTML = old;
            if (res.error) { alert(res.error); return; }
            nodes = res.nodes; draw();
            if (res.notes && res.notes.length) alert(res.notes.join('\n'));
        });
    }
    draw();
})();
</script>
