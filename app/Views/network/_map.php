<?php
/**
 * Klantenkaart (Leaflet + OpenStreetMap). Stip per klantlocatie, kleur naar status.
 *
 * @var array $map   CustomerMap::points()
 * @var bool  $full  volledige pagina (anders: kaart op het dashboard)
 */
$full ??= false;
$c    = $map['counts'];
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">

<div class="card mb-4 cmap-card">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <span class="text-nowrap"><i class="bi bi-geo-alt"></i> Klantenkaart</span>
        <div class="btn-group btn-group-sm cmap-filter" role="group" aria-label="Filter">
            <button type="button" class="btn btn-outline-secondary" data-filter="all">Alles</button>
            <button type="button" class="btn btn-outline-secondary" data-filter="issues">Storingen <span class="badge text-bg-danger cmap-n-issues"><?= $c['down'] + $c['warn'] ?></span></button>
            <button type="button" class="btn btn-outline-secondary" data-filter="monitored">Bewaakt</button>
        </div>
        <?php if (! $full) : ?><a href="<?= site_url('netwerk/kaart') ?>" class="small text-nowrap"><i class="bi bi-arrows-fullscreen"></i> Groot</a><?php endif ?>
    </div>
    <div class="cmap-wrap">
        <div id="cmap" class="cmap <?= $full ? 'cmap-full' : '' ?>" role="region" aria-label="Kaart met klantlocaties"></div>
        <div class="cmap-touch d-none" id="cmap-touch"><i class="bi bi-hand-index"></i> Tik om de kaart te bewegen</div>
        <div class="cmap-empty d-none" id="cmap-empty">Geen locaties om te tonen.</div>
    </div>
    <div class="card-footer small text-muted d-flex flex-wrap gap-3 align-items-center">
        <span><span class="cm-dot cm-down cm-static"></span> offline <strong class="cmap-n-down"><?= $c['down'] ?></strong></span>
        <span><span class="cm-dot cm-warn cm-static"></span> VPN <strong class="cmap-n-warn"><?= $c['warn'] ?></strong></span>
        <span><span class="cm-dot cm-ok cm-static"></span> in orde <strong class="cmap-n-ok"><?= $c['ok'] ?></strong></span>
        <span><span class="cm-dot cm-none cm-static"></span> niet bewaakt <strong class="cmap-n-none"><?= $c['none'] ?></strong></span>
        <span class="ms-auto" id="cmap-note"></span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function () {
    if (!window.L) return;
    const FULL = <?= $full ? 'true' : 'false' ?>;
    const DATA_URL = <?= json_encode(site_url('netwerk/kaart/data')) ?>;
    const GEO_URL = <?= json_encode(site_url('netwerk/kaart/adressen')) ?>;
    const CUST_URL = <?= json_encode(site_url('klanten/')) ?>;
    const TOPO_URL = <?= json_encode(site_url('netwerk/topologie/')) ?>;
    const RANK = { down: 3, warn: 2, ok: 1, none: 0 };
    const LABEL = { down: 'Offline', warn: 'VPN niet in orde', ok: 'Alles online', none: 'Niet bewaakt' };
    let data = <?= json_encode($map, JSON_UNESCAPED_UNICODE) ?>;
    let filter = 'all';
    try { filter = localStorage.getItem('cmap.filter') || 'all'; } catch (e) {}

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const touch = window.matchMedia('(pointer: coarse)').matches;

    const map = L.map('cmap', { scrollWheelZoom: FULL, zoomControl: true, attributionControl: true, tap: true });
    // OpenStreetMap: geen sleutel nodig (CARTO vraagt er sinds kort wel een).
    // OSM wil weten van welke site de aanvraag komt: zonder herkomst geeft het 403.
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-bijdragers'
    }).addTo(map);
    map.setView([51.6, 5.6], 8);

    // Op het dashboard: niet per ongeluk de kaart verslepen tijdens het scrollen
    if (!FULL) {
        map.on('focus click', () => map.scrollWheelZoom.enable());
        map.on('blur mouseout', () => map.scrollWheelZoom.disable());
        if (touch) {
            map.dragging.disable();
            const hint = document.getElementById('cmap-touch');
            hint.classList.remove('d-none');
            hint.addEventListener('click', () => { map.dragging.enable(); hint.classList.add('d-none'); });
        }
    }

    const cluster = L.markerClusterGroup({
        showCoverageOnHover: false, maxClusterRadius: 40, spiderfyOnMaxZoom: true,
        iconCreateFunction: cl => {
            let worst = 'none', n = 0;
            cl.getAllChildMarkers().forEach(m => { if (RANK[m.options.level] > RANK[worst]) worst = m.options.level; if (m.options.level === worst) n++; });
            const all = cl.getChildCount();
            return L.divIcon({ html: '<span>' + all + '</span>', className: 'cm-cluster cm-' + worst, iconSize: [34, 34] });
        }
    }).addTo(map);

    function popup(p) {
        let h = '<div class="cm-pop"><div class="cm-pop-title"><a href="' + CUST_URL + p.cid + '">' + esc(p.customer) + '</a></div>';
        if (p.site) h += '<div class="fw-semibold">' + esc(p.site) + '</div>';
        if (p.address) h += '<div class="text-muted small">' + esc(p.address) + '</div>';
        h += '<div class="cm-pop-state cm-t-' + p.level + '">' + LABEL[p.level] + (p.level === 'down' ? ': ' + p.offCount + ' van ' + p.devices : (p.devices ? ' · ' + p.devices + ' apparaten' : '')) + '</div>';
        if (p.offline.length) h += '<ul class="cm-pop-list">' + p.offline.map(d => '<li>' + esc(d.name) + (d.since ? ' <span class="text-muted">· ' + esc(d.since) + '</span>' : '') + '</li>').join('') + (p.offCount > p.offline.length ? '<li class="text-muted">en ' + (p.offCount - p.offline.length) + ' meer</li>' : '') + '</ul>';
        if (p.vpn.length) h += '<ul class="cm-pop-list">' + p.vpn.map(v => '<li><i class="bi bi-shield-lock"></i> ' + esc(v) + '</li>').join('') + '</ul>';
        if (p.topo.length) h += '<div class="small mt-1">' + p.topo.map(t => '<a href="' + TOPO_URL + t.id + '"><i class="bi bi-diagram-3"></i> ' + esc(t.name) + '</a>').join('<br>') + '</div>';
        return h + '</div>';
    }

    const shown = p => filter === 'issues' ? (p.level === 'down' || p.level === 'warn') : filter === 'monitored' ? p.level !== 'none' : true;

    function render(refit) {
        cluster.clearLayers();
        const pts = data.points.filter(shown);
        const markers = pts.map(p => L.marker([p.lat, p.lng], {
            level: p.level, title: p.customer + (p.site ? ' · ' + p.site : ''), riseOnHover: true,
            zIndexOffset: RANK[p.level] * 1000,
            icon: L.divIcon({ html: '', className: 'cm-dot cm-' + p.level, iconSize: p.level === 'down' ? [18, 18] : [14, 14] })
        }).bindPopup(popup(p), { maxWidth: 280 }));
        cluster.addLayers(markers);
        document.getElementById('cmap-empty').classList.toggle('d-none', pts.length > 0);
        if (refit && pts.length) {
            map.fitBounds(L.latLngBounds(pts.map(p => [p.lat, p.lng])).pad(0.15), { maxZoom: 13 });
        }
        const c = data.counts;
        document.querySelectorAll('.cmap-n-down').forEach(e => e.textContent = c.down);
        document.querySelectorAll('.cmap-n-warn').forEach(e => e.textContent = c.warn);
        document.querySelectorAll('.cmap-n-ok').forEach(e => e.textContent = c.ok);
        document.querySelectorAll('.cmap-n-none').forEach(e => e.textContent = c.none);
        document.querySelectorAll('.cmap-n-issues').forEach(e => e.textContent = c.down + c.warn);
        document.querySelectorAll('.cmap-filter [data-filter]').forEach(b => b.classList.toggle('active', b.dataset.filter === filter));
    }

    document.querySelectorAll('.cmap-filter [data-filter]').forEach(b => b.addEventListener('click', () => {
        filter = b.dataset.filter;
        try { localStorage.setItem('cmap.filter', filter); } catch (e) {}
        render(true);
    }));

    function note(text) { document.getElementById('cmap-note').textContent = text; }
    function noteDefault() {
        const el = document.getElementById('cmap-note');
        const parts = [data.pending ? data.pending + ' adressen nog op te zoeken' : '', data.noAddress ? data.noAddress + ' zonder adres' : ''].filter(Boolean).map(esc);
        const nf = data.notFound || [];
        if (nf.length) parts.push('<a href="' + (FULL ? '#cmap-notfound' : <?= json_encode(site_url('netwerk/kaart')) ?> + '#cmap-notfound') + '" title="' + esc(nf.map(n => n.name).join(', ')) + '">' + nf.length + ' adres' + (nf.length === 1 ? '' : 'sen') + ' niet gevonden</a>');
        el.innerHTML = parts.join(' · ');
    }

    // Adressen zonder coördinaten: een paar per keer opzoeken
    async function geocode() {
        const meta = document.querySelector('meta[name="csrf-hash"]'), name = document.querySelector('meta[name="csrf-name"]');
        if (!meta || !name) return;
        for (let i = 0; i < 20 && data.pending > 0; i++) {
            note('Adressen opzoeken… (' + data.pending + ' te gaan)');
            const body = new FormData(); body.append(name.content, meta.content);
            const res = await fetch(GEO_URL, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(() => null);
            const json = res ? await res.json().catch(() => null) : null;
            if (!json || json.error) break;
            if (json.csrf) meta.content = json.csrf;
            const before = data.points.length;
            data = json;
            render(data.points.length !== before);
            if (!json.done && !json.failed) break;
        }
        noteDefault();
    }

    async function refresh() {
        const res = await fetch(DATA_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(() => null);
        const json = res && res.ok ? await res.json().catch(() => null) : null;
        if (json && json.points) { data = json; render(false); noteDefault(); }
    }

    render(true);
    noteDefault();
    if (data.pending > 0) geocode();
    setInterval(() => { if (!document.hidden) refresh(); }, 120000);
})();
</script>
