<?php
/**
 * Afdrukversie van een topologie (Opslaan als PDF via het afdrukvenster).
 *
 * @var array      $site
 * @var array|null $loc
 * @var list<array> $nodes
 * @var array      $types
 * @var array      $manualIcons
 */
use App\Libraries\Network;

$icons = $manualIcons;
foreach (device_types() as $k => $t) {
    $icons[$k] = $t[1];
}
$title = 'Netwerktopologie ' . ($loc ? $loc['customer_name'] : $site['name']);
?><!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title . ' - ' . date('d-m-Y')) ?></title>
    <link href="<?= base_url('assets/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <style id="page-size">@page { size: A4 landscape; margin: 10mm; }</style>
    <style>
        :root { --blue: #0b1150; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font: 13px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2433; background: #eef0f4; }
        .sheet { background: #fff; max-width: 1180px; margin: 16px auto; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px; background: #fff; border-bottom: 1px solid #dde0e7; flex-wrap: wrap; }
        .bar button, .bar a, .bar select { font: inherit; padding: 8px 14px; border-radius: 8px; border: 1px solid #c9cdd8; background: #fff; color: #1d2433; text-decoration: none; cursor: pointer; }
        .bar .primary { background: var(--blue); border-color: var(--blue); color: #fff; font-weight: 600; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid var(--blue); padding-bottom: 10px; margin-bottom: 12px; }
        header img { height: 34px; }
        h1 { font-size: 20px; margin: 0 0 2px; color: var(--blue); }
        .meta { color: #5b6272; font-size: 12px; }
        .meta strong { color: #1d2433; }
        .right { text-align: right; }
        #topo { width: 100%; height: auto; display: block; max-height: 150mm; }
        .edge { fill: none; stroke: #8f96a6; stroke-width: 2; }
        .e-wireless { stroke: #3b82f6; stroke-dasharray: 5 4; }
        .e-ptp { stroke: #e8590c; stroke-width: 3; stroke-dasharray: 9 5; }
        .e-vpn { stroke: #7c3aed; stroke-dasharray: 2 4; }
        .elabel { font: 600 10.5px system-ui, sans-serif; fill: #495062; paint-order: stroke; stroke: #fff; stroke-width: 4px; stroke-linejoin: round; }
        .node rect { fill: #fff; stroke: #c3c8d3; stroke-width: 1.2; }
        .node.st-offline.mon rect { stroke: #dc3545; stroke-width: 2; fill: #fff6f7; }
        .node.k-manual rect { fill: #fafafa; stroke-dasharray: 5 4; stroke: #aab0bd; }
        .node.k-vault rect { fill: #fffaf0; stroke: #e0c48a; }
        .node.foreign rect { fill: #f3faf6; }
        .sdot { fill: #adb5bd; } .st-online .sdot { fill: #20a35a; } .st-offline .sdot { fill: #dc3545; }
        .nicon { width: 30px; height: 30px; border-radius: 8px; background: #eef0fb; color: var(--blue); display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .k-vault .nicon { background: #fdf1d9; color: #9a6b00; } .k-manual .nicon { background: #f1f2f5; color: #5b6272; }
        .nname { font: 600 13px system-ui, sans-serif; fill: #1d2433; }
        .nsub { font: 11px system-ui, sans-serif; fill: #6c757d; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .nsub.mono { font-size: 10.5px; }
        .nplat { font: 700 9px system-ui, sans-serif; fill: #157347; letter-spacing: .04em; }
        .legend { display: flex; flex-wrap: wrap; gap: 4px 18px; font-size: 11px; color: #5b6272; margin: 8px 0 0; align-items: center; }
        .legend svg { vertical-align: middle; }
        h2 { font-size: 15px; color: var(--blue); margin: 18px 0 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        th { text-align: left; background: #f1f3f8; color: #3a4152; font-weight: 600; padding: 5px 7px; border-bottom: 1px solid #d5d9e2; }
        td { padding: 4px 7px; border-bottom: 1px solid #eceef3; vertical-align: top; }
        tr { break-inside: avoid; }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd; margin-right: 4px; }
        .dot.online { background: #20a35a; } .dot.offline { background: #dc3545; }
        .tree { color: #aab0bd; }
        footer { margin-top: 14px; font-size: 10.5px; color: #8a90a0; display: flex; justify-content: space-between; }
        .table-part { break-before: page; }
        .table-part.inline { break-before: auto; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
<div class="bar">
    <button class="primary" onclick="window.print()"><i class="bi bi-file-earmark-pdf"></i> Opslaan als PDF / afdrukken</button>
    <select id="paper" aria-label="Papierformaat">
        <option value="A4 landscape">A4 liggend</option>
        <option value="A3 landscape">A3 liggend</option>
        <option value="A4 portrait">A4 staand</option>
    </select>
    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" id="with-table" checked> Apparatenlijst</label>
    <a href="<?= site_url('netwerk/topologie/' . $site['id']) ?>"><i class="bi bi-arrow-left"></i> Terug</a>
</div>

<div class="sheet">
    <header>
        <div>
            <h1><?= esc($title) ?></h1>
            <div class="meta">
                <?php if ($loc) : ?><strong><?= esc($loc['name']) ?></strong><?php $addr = trim(implode(' ', array_filter([$loc['address'] ?? null, $loc['zipcode'] ?? null, $loc['city'] ?? null]))); ?><?= $addr !== '' ? ' · ' . esc($addr) : '' ?><br><?php endif ?>
                <?= esc(Network::platformLabel($site['platform'])) ?>-site <strong><?= esc($site['name']) ?></strong> · <?= count($nodes) ?> apparaten
                <?php foreach (Network::vpn($site) as $v) : ?><br>SD-WAN <strong><?= esc($v['name']) ?></strong> (<?= $v['role'] ?>, <?= esc(Network::vpnState($v['state'])[1]) ?><?= $v['total'] ? ', ' . $v['up'] . '/' . $v['total'] . ' tunnels' : '' ?>)<?= $v['peers'] ? ' met ' . esc(implode(', ', array_column($v['peers'], 'name'))) : '' ?><?php endforeach ?>
            </div>
        </div>
        <div class="right">
            <img src="<?= base_url('img/logo.png') ?>" alt="bongersonline"><br>
            <span class="meta"><?= date('d-m-Y H:i') ?></span>
        </div>
    </header>

    <svg id="topo" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Topologie"></svg>
    <div class="legend">
        <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="edge"/></svg> kabel</span>
        <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="edge e-wireless"/></svg> wifi</span>
        <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="edge e-ptp"/></svg> straalverbinding</span>
        <span><svg width="28" height="8"><line x1="0" y1="4" x2="28" y2="4" class="edge e-vpn"/></svg> VPN</span>
        <span><span class="dot online"></span>online <span class="dot offline" style="margin-left:6px"></span>offline</span>
        <span>EIGEN = niet-beheerd apparaat · KLUIS = uit de apparatenkluis</span>
    </div>

    <div class="table-part" id="table-part">
        <h2>Apparaten</h2>
        <table>
            <thead><tr><th>Apparaat</th><th>Model</th><th>IP-adres</th><th>MAC-adres</th><th>Hangt aan</th><th>Poort</th><th>Verbinding</th><th>Status</th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>

    <footer><span>bongersonline · Klantbeheer</span><span><?= esc($site['name']) ?> · <?= date('d-m-Y') ?></span></footer>
</div>

<script>
(function () {
    const nodes = <?= json_encode($nodes, JSON_UNESCAPED_UNICODE) ?>;
    const ICONS = <?= json_encode($icons) ?>;
    const TYPES = <?= json_encode($types, JSON_UNESCAPED_UNICODE) ?>;
    const NS = 'http://www.w3.org/2000/svg', W = 176, H = 62, GX = 22, GY = 80;
    const svg = document.getElementById('topo');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const cut = (s, n) => { s = String(s ?? ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; };
    const el = (tag, attrs, parent) => { const e = document.createElementNS(NS, tag); Object.entries(attrs || {}).forEach(([k, v]) => e.setAttribute(k, v)); if (parent) parent.appendChild(e); return e; };

    // Zelfde indeling als op het scherm: boom van boven naar beneden
    const byId = Object.fromEntries(nodes.map(n => [n.id, n]));
    const kids = {};
    nodes.forEach(n => { if (n.parent && byId[n.parent]) (kids[n.parent] ||= []).push(n); });
    const portNum = p => { const m = String(p ?? '').match(/\d+/); return m ? +m[0] : 9999; };
    Object.values(kids).forEach(l => l.sort((a, b) => portNum(a.parent_port) - portNum(b.parent_port) || a.name.localeCompare(b.name)));
    const rank = n => (n.type === 'router' ? 0 : n.type === 'switch' ? 1 : 2);
    const roots = nodes.filter(n => !n.parent || !byId[n.parent]).sort((a, b) => rank(a) - rank(b) || (kids[b.id]?.length || 0) - (kids[a.id]?.length || 0) || a.name.localeCompare(b.name));
    const pos = {}, order = [], guard = new Set();
    let x = 0, maxDepth = 0;
    const place = (n, depth) => {
        if (guard.has(n.id)) return;
        guard.add(n.id); order.push([n, depth]);
        maxDepth = Math.max(maxDepth, depth);
        const ch = (kids[n.id] || []).filter(c => !guard.has(c.id));
        ch.forEach(c => place(c, depth + 1));
        const xs = ch.map(c => pos[c.id]?.x).filter(Number.isFinite);
        if (!xs.length) { pos[n.id] = { x, y: depth * (H + GY) }; x += W + GX; return; }
        pos[n.id] = { x: (Math.min(...xs) + Math.max(...xs)) / 2, y: depth * (H + GY) };
    };
    roots.forEach(r => { place(r, 0); x += GX * 2; });
    nodes.forEach(n => { if (!guard.has(n.id)) { place(n, 0); x += GX * 2; } });
    const bw = Math.max(x - GX * 3, W), bh = (maxDepth + 1) * (H + GY) - GY, pad = 16;
    svg.setAttribute('viewBox', `${-pad} ${-pad} ${bw + pad * 2} ${bh + pad * 2}`);

    nodes.forEach(n => {
        if (!n.parent || !pos[n.parent] || !pos[n.id]) return;
        const p = pos[n.parent], c = pos[n.id], x1 = p.x + W / 2, y1 = p.y + H, x2 = c.x + W / 2, y2 = c.y, my = (y1 + y2) / 2;
        el('path', { d: `M${x1},${y1} C${x1},${my} ${x2},${my} ${x2},${y2}`, class: 'edge e-' + (n.link || 'wired') }, svg);
    });
    nodes.forEach(n => {
        if (!n.parent || !pos[n.parent] || !pos[n.id]) return;
        const c = pos[n.id];
        const txt = [n.parent_port ? 'poort ' + n.parent_port : (n.local_port ? 'eigen poort ' + n.local_port : ''), n.link && n.link !== 'wired' ? (n.link === 'ptp' ? 'PTP' : n.link === 'vpn' ? 'VPN' : 'wifi') : ''].filter(Boolean).join(' · ');
        if (txt) { const t = el('text', { x: c.x + W / 2, y: c.y - 10, class: 'elabel', 'text-anchor': 'middle' }, svg); t.textContent = txt; }
    });
    nodes.forEach(n => {
        const p = pos[n.id]; if (!p) return;
        const b = el('g', { class: 'node st-' + n.status + (n.monitor ? ' mon' : '') + (n.foreign && n.kind === 'network' ? ' foreign' : '') + ' k-' + n.kind, transform: `translate(${p.x},${p.y})` }, svg);
        el('rect', { width: W, height: H, rx: 10 }, b);
        if (n.kind === 'network') el('circle', { cx: W - 14, cy: 14, r: 5, class: 'sdot' }, b);
        const fo = el('foreignObject', { x: 8, y: 8, width: 30, height: 30 }, b);
        fo.innerHTML = '<div xmlns="http://www.w3.org/1999/xhtml" class="nicon"><i class="bi bi-' + esc(ICONS[n.type] || 'hdd-network') + '"></i></div>';
        el('text', { x: 44, y: 24, class: 'nname' }, b).textContent = cut(n.name, 17);
        el('text', { x: 44, y: 41, class: 'nsub' }, b).textContent = cut(n.model || '', 20);
        el('text', { x: 44, y: 55, class: 'nsub mono' }, b).textContent = cut(n.ip || n.mac || '', 22);
        const tag = n.kind === 'manual' ? 'EIGEN' : n.kind === 'vault' ? 'KLUIS' : (n.foreign ? n.platform.toUpperCase() : '');
        if (tag) el('text', { x: W - 8, y: H - 6, class: 'nplat', 'text-anchor': 'end' }, b).textContent = tag;
    });

    // Tabel in boomvolgorde
    const linkName = { wired: 'Kabel', wireless: 'Wifi', ptp: 'Straalverbinding', vpn: 'VPN' };
    document.getElementById('rows').innerHTML = order.map(([n, d]) => {
        const p = byId[n.parent];
        const status = n.kind !== 'network' ? '' : '<span class="dot ' + esc(n.status) + '"></span>' + (n.status === 'online' ? 'online' : n.status === 'offline' ? 'offline' : '–');
        return '<tr><td><span class="tree">' + '&nbsp;&nbsp;&nbsp;'.repeat(d) + (d ? '└ ' : '') + '</span>' + esc(n.name) + (n.kind === 'manual' ? ' <small>(eigen)</small>' : '') + '</td>'
            + '<td>' + esc(n.model || '') + '</td><td class="mono">' + esc(n.ip || '') + '</td><td class="mono">' + esc(n.mac || '') + '</td>'
            + '<td>' + esc(p ? p.name : '') + '</td><td>' + esc(n.parent_port || '') + '</td><td>' + esc(p ? (linkName[n.link] || '') : '') + '</td><td>' + status + '</td></tr>';
    }).join('');

    // Instellingen
    const paper = document.getElementById('paper'), withTable = document.getElementById('with-table'), tablePart = document.getElementById('table-part');
    paper.addEventListener('change', () => { document.getElementById('page-size').textContent = '@page { size: ' + paper.value + '; margin: 10mm; }'; });
    withTable.addEventListener('change', () => { tablePart.style.display = withTable.checked ? '' : 'none'; });
    // Kleine boom: tabel op dezelfde pagina
    if (maxDepth <= 2 && nodes.length <= 8) tablePart.classList.add('inline');

    // Meteen het afdrukvenster openen (als de iconen geladen zijn)
    (document.fonts ? document.fonts.ready : Promise.resolve()).then(() => setTimeout(() => window.print(), 400));
})();
</script>
</body>
</html>
