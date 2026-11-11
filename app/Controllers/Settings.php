<?php

namespace App\Controllers;

use App\Libraries\Moneybird;
use App\Libraries\Vault;
use App\Models\CustomerModel;
use App\Models\VaultLogModel;
use Throwable;

class Settings extends BaseController
{
    public function moneybird()
    {
        $mb   = new Moneybird();
        $data = ['mb' => $mb, 'taxRates' => [], 'products' => [], 'error' => null, 'vaultReady' => Vault::ready()];

        if ($mb->configured()) {
            try {
                $data['taxRates'] = $mb->taxRates();
                $data['products'] = $mb->products();
            } catch (Throwable $e) {
                $data['error'] = $e->getMessage();
            }
        }

        $customers = model(CustomerModel::class);
        $data['linked']   = $customers->where('moneybird_contact_id IS NOT NULL')->countAllResults();
        $data['unlinked'] = $customers->where('moneybird_contact_id', null)->countAllResults();

        return $this->page('settings/moneybird', $data, 'Moneybird');
    }

    /**
     * Haalt alle Moneybird-contacten op en koppelt ze aan klanten:
     * eerst op contact-ID, dan op exacte naam. Optioneel nieuwe klanten aanmaken.
     */
    public function importContacts()
    {
        $createNew = (bool) $this->request->getPost('create_new');

        try {
            $contacts = (new Moneybird())->contacts();
        } catch (Throwable $e) {
            return $this->back($e->getMessage(), 'error');
        }

        $model   = model(CustomerModel::class);
        $byMbId  = array_column($model->where('moneybird_contact_id IS NOT NULL')->findAll(), null, 'moneybird_contact_id');
        $byName  = [];
        foreach ($model->where('moneybird_contact_id', null)->findAll() as $c) {
            $byName[mb_strtolower(trim($c['name']))] = $c;
        }

        $linked = $created = $updated = 0;
        foreach ($contacts as $mc) {
            $id   = (string) $mc['id'];
            $name = trim((string) ($mc['company_name'] ?: trim(($mc['firstname'] ?? '') . ' ' . ($mc['lastname'] ?? ''))));
            if ($name === '') {
                continue;
            }
            $fields = array_filter([
                'contact_name' => trim(($mc['firstname'] ?? '') . ' ' . ($mc['lastname'] ?? '')) ?: null,
                'email'        => $mc['email'] ?? null,
                'phone'        => $mc['phone'] ?? null,
                'address'      => $mc['address1'] ?? null,
                'zipcode'      => $mc['zipcode'] ?? null,
                'city'         => $mc['city'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            if (isset($byMbId[$id])) {
                // Alleen lege velden aanvullen, niets overschrijven
                $existing = $byMbId[$id];
                $fill = array_filter($fields, static fn ($v, $k) => empty($existing[$k]), ARRAY_FILTER_USE_BOTH);
                if ($fill !== []) {
                    $model->update($existing['id'], $fill);
                    $updated++;
                }
            } elseif (isset($byName[mb_strtolower($name)])) {
                $model->update($byName[mb_strtolower($name)]['id'], ['moneybird_contact_id' => $id]);
                unset($byName[mb_strtolower($name)]);
                $linked++;
            } elseif ($createNew) {
                $model->insert(['name' => $name, 'moneybird_contact_id' => $id, 'active' => 1] + $fields);
                $created++;
            }
        }

        return $this->back(sprintf('%d contacten opgehaald: %d gekoppeld op naam, %d nieuw aangemaakt, %d aangevuld.', count($contacts), $linked, $created, $updated));
    }

    public function vaultLog()
    {
        $log = model(VaultLogModel::class)
            ->select('vault_log.*, users.username, auth_identities.secret AS email, devices.name AS device_name, customers.name AS customer_name')
            ->join('users', 'users.id = vault_log.user_id', 'left')
            ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'", 'left')
            ->join('devices', 'devices.id = vault_log.device_id', 'left')
            ->join('customers', 'customers.id = vault_log.customer_id', 'left')
            ->orderBy('vault_log.id', 'DESC')->findAll(1000);

        return $this->page('settings/vaultlog', ['log' => $log], 'Kluislogboek');
    }

    /**
     * Diagnose: database- en catalogusstatus, om problemen op de server te vinden.
     */
    public function diagnose()
    {
        $db   = db_connect();
        $info = [
            'PHP'         => PHP_VERSION,
            'Omgeving'    => ENVIRONMENT,
            'Database'    => $db->getPlatform() . ' ' . $db->getVersion() . ' · ' . $db->getDatabase(),
            'DBPrefix'    => $db->getPrefix() === '' ? '(geen)' : $db->getPrefix(),
            'DBDebug'     => var_export($db->DBDebug, true),
            'Migraties'   => implode(', ', array_column($db->table('migrations')->select('version')->orderBy('id', 'DESC')->limit(3)->get()->getResultArray(), 'version')),
        ];

        $tables = [];
        foreach (['suppliers', 'supplier_groups', 'products', 'quotes', 'quote_lines', 'customers', 'subscriptions'] as $t) {
            $tables[$t] = $db->tableExists($t) ? $db->table($t)->countAllResults() : 'ontbreekt';
        }

        $engines = [];
        $mysql   = stripos($db->getPlatform(), 'mysql') !== false;
        if ($mysql) {
            foreach ($db->query('SHOW TABLE STATUS WHERE Name IN (\'products\', \'suppliers\')')->getResultArray() as $r) {
                $engines[$r['Name']] = $r['Engine'] . ' · ' . $r['Collation'] . ' · auto_increment ' . $r['Auto_increment'];
            }
            try {
                $vars = $db->query('SELECT @@autocommit AS autocommit, @@sql_mode AS sql_mode, @@auto_increment_increment AS inc')->getRowArray();
                $info['MySQL'] = 'autocommit=' . $vars['autocommit'] . ' · increment=' . $vars['inc'] . ' · ' . $vars['sql_mode'];
            } catch (\Throwable $e) {
                $info['MySQL'] = 'onbekend: ' . $e->getMessage();
            }
        }

        // Proef: één product opslaan in een transactie en kijken of het blijft staan
        $test = 'niet uitgevoerd';
        try {
            $supplier = $db->table('suppliers')->where('code', 'osec')->get()->getRowArray();
            $db->transStart();
            $db->table('products')->insert(['supplier_id' => $supplier['id'], 'supplier_sku' => 'DIAG-' . time(), 'name' => 'Diagnose-test', 'source' => 'diag', 'active' => 0]);
            $id = $db->insertID();
            $db->transComplete();
            $found = $db->table('products')->where('id', $id)->countAllResults();
            $db->table('products')->where('source', 'diag')->delete();
            $test = $found ? 'OK (opgeslagen en weer verwijderd, id ' . $id . ')' : 'MISLUKT: na transactie niet terug te vinden';
        } catch (\Throwable $e) {
            $test = 'FOUT: ' . $e->getMessage();
        }

        return $this->page('settings/diagnose', [
            'info'      => $info,
            'tables'    => $tables,
            'engines'   => $engines,
            'suppliers' => $db->table('suppliers')->get()->getResultArray(),
            'perSupplier' => $db->table('products')->select('supplier_id, source, active, COUNT(*) AS n')->groupBy('supplier_id, source, active')->get()->getResultArray(),
            'test'      => $test,
            'log'       => $this->lastLogLines(),
        ], 'Diagnose');
    }

    private function lastLogLines(): array
    {
        $files = glob(WRITEPATH . 'logs/log-*.log') ?: [];
        rsort($files);
        $lines = [];
        foreach (array_slice($files, 0, 2) as $f) {
            foreach (array_reverse(file($f, FILE_IGNORE_NEW_LINES) ?: []) as $l) {
                if (preg_match('/^(ERROR|CRITICAL|ALERT|EMERGENCY)/', $l)) {
                    $lines[] = mb_substr($l, 0, 400);
                }
                if (count($lines) >= 15) {
                    break 2;
                }
            }
        }

        return $lines;
    }
}
