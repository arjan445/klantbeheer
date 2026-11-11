<h3 class="mb-1">Diagnose</h3>
<p class="text-muted small">Selecteer alles hieronder en stuur het door als er iets niet werkt. Er staan geen wachtwoorden in.</p>
<div class="card"><div class="card-body">
<pre class="mb-0 small" style="white-space: pre-wrap"><?php
foreach ($info as $k => $v) { echo esc(str_pad($k, 12) . ': ' . $v) . "\n"; }
echo "\nTabellen:\n";
foreach ($tables as $k => $v) { echo esc('  ' . str_pad($k, 16) . $v) . "\n"; }
foreach ($engines as $k => $v) { echo esc('  ' . str_pad($k, 16) . $v) . "\n"; }
echo "\nLeveranciers:\n";
foreach ($suppliers as $s) { echo esc('  id ' . $s['id'] . ' · ' . $s['name'] . ' · code ' . ($s['code'] ?? '-') . ' · laatste import ' . ($s['last_import_at'] ?? '-')) . "\n"; }
echo "\nProducten per leverancier:\n";
foreach ($perSupplier as $r) { echo esc('  leverancier ' . $r['supplier_id'] . ' · ' . $r['source'] . ' · actief ' . $r['active'] . ' · ' . $r['n']) . "\n"; }
if ($perSupplier === []) { echo "  (geen)\n"; }
echo "\nTransactietest: " . esc($test) . "\n";
echo "\nLaatste fouten in het log:\n";
foreach ($log as $l) { echo esc('  ' . $l) . "\n"; }
if ($log === []) { echo "  (geen)\n"; }
?></pre>
</div></div>
