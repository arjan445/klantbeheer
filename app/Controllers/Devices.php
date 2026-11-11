<?php

namespace App\Controllers;

use App\Libraries\Vault;
use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\SiteModel;
use App\Models\VaultLogModel;
use Throwable;

/**
 * Kluis: apparaten, IP-adressen en wachtwoorden per klant en locatie.
 * Wachtwoorden en geheime notities worden versleuteld opgeslagen en alleen
 * op verzoek (met logregel) getoond.
 */
class Devices extends BaseController
{
    private const FIELDS = ['customer_id', 'site_id', 'type', 'name', 'room', 'brand', 'model', 'serial', 'firmware', 'ip', 'port', 'mac', 'url', 'username', 'notes', 'installed_at'];

    private function rules(): array
    {
        return [
            'customer_id'  => 'required|is_natural_no_zero|is_not_unique[customers.id]',
            'site_id'      => 'permit_empty|is_natural_no_zero',
            'type'         => 'required|in_list[' . implode(',', array_keys(device_types())) . ']',
            'name'         => 'required|max_length[255]',
            'url'          => 'permit_empty|max_length[255]',
            'room'         => 'permit_empty|max_length[100]',
            'installed_at' => 'permit_empty|valid_date[Y-m-d]',
        ];
    }

    public function index()
    {
        $type = $this->request->getGet('soort');

        $model = model(DeviceModel::class)
            ->select('devices.*, customers.name AS customer_name, sites.name AS site_name')
            ->join('customers', 'customers.id = devices.customer_id')
            ->join('sites', 'sites.id = devices.site_id', 'left')
            ->orderBy('customers.name')->orderBy('sites.name')->orderBy('devices.name');
        if ($type) {
            $model->where('devices.type', $type);
        }

        return $this->page('devices/index', [
            'devices' => $model->findAll(),
            'type'    => $type,
            'ready'   => Vault::ready(),
        ], 'Apparaten & wachtwoorden');
    }

    public function show($id)
    {
        $device = $this->findOr404(DeviceModel::class, $id);

        return $this->page('devices/show', [
            'device'   => $device,
            'customer' => model(CustomerModel::class)->find($device['customer_id']),
            'site'     => $device['site_id'] ? model(SiteModel::class)->find($device['site_id']) : null,
            'log'      => model(VaultLogModel::class)
                ->select('vault_log.*, users.username, auth_identities.secret AS email')
                ->join('users', 'users.id = vault_log.user_id', 'left')
                ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'", 'left')
                ->where('device_id', $id)->orderBy('vault_log.id', 'DESC')->limit(20)->findAll(),
        ], $device['name']);
    }

    public function create()
    {
        $values = [
            'customer_id' => $this->request->getGet('klant'),
            'site_id'     => $this->request->getGet('locatie'),
            'type'        => $this->request->getGet('soort') ?? 'camera',
        ];

        // "Kopie maken" / "Opslaan & volgende": velden overnemen (zonder wachtwoord en serienummer)
        if ($copyId = $this->request->getGet('kopie')) {
            $copy = model(DeviceModel::class)->find($copyId);
            if ($copy) {
                $values         = array_intersect_key($copy, array_flip(self::FIELDS));
                $values['name'] = $this->nextName($copy['name']);
                $values['ip']   = $this->nextIp($copy['ip']);
                $values['serial'] = null;
                $values['mac']    = null;
                $values['room']   = null;
                $values['copied_from'] = $copy['id'];
            }
        }

        return $this->form(null, $values);
    }

    public function edit($id)
    {
        $device = $this->findOr404(DeviceModel::class, $id);

        return $this->form($device, $device);
    }

    private function form(?array $existing, array $values)
    {
        $title = $existing ? 'Apparaat bewerken' : 'Apparaat / wachtwoord toevoegen';
        $data  = [
            'device'    => $values,
            'isNew'     => $existing === null,
            'customers' => model(CustomerModel::class)->orderBy('name')->findAll(),
            'sites'     => model(SiteModel::class)->orderBy('name')->findAll(),
            'ready'     => Vault::ready(),
        ];

        if (! $this->request->is('post')) {
            return $this->page('devices/form', $data, $title);
        }

        $post  = $this->nullify($this->request->getPost(self::FIELDS));
        $valid = $this->validateData($post, $this->rules());

        if ($valid && $post['site_id'] !== null) {
            $site = model(SiteModel::class)->find($post['site_id']);
            if ($site === null || (int) $site['customer_id'] !== (int) $post['customer_id']) {
                $post['site_id'] = null;
            }
        }

        if (! $valid) {
            $data['device']     = $post + ['id' => $existing['id'] ?? null];
            $data['validation'] = true;

            return $this->page('devices/form', $data, $title);
        }

        // Geheimen: leeg veld = ongewijzigd laten, tenzij "wissen" is aangevinkt
        $password = (string) $this->request->getPost('password');
        $secret   = (string) $this->request->getPost('secret_notes');
        $changed  = false;

        try {
            if ($this->request->getPost('clear_password')) {
                $post['password_enc'] = null;
                $changed = true;
            } elseif ($password !== '') {
                $post['password_enc'] = Vault::encrypt($password);
                $changed = true;
            }
            if ($this->request->getPost('clear_secret')) {
                $post['secret_notes_enc'] = null;
                $changed = true;
            } elseif (trim($secret) !== '') {
                $post['secret_notes_enc'] = Vault::encrypt($secret);
                $changed = true;
            }
        } catch (Throwable $e) {
            $data['device'] = $post + ['id' => $existing['id'] ?? null];
            $this->session->setFlashdata('error', $e->getMessage());

            return $this->page('devices/form', $data, $title);
        }

        if ($existing) {
            model(DeviceModel::class)->update($existing['id'], $post);
            $id = $existing['id'];
        } else {
            $id = model(DeviceModel::class)->insert($post);
        }

        if ($changed) {
            Vault::log($existing ? 'gewijzigd' : 'aangemaakt', ['id' => $id, 'customer_id' => $post['customer_id']]);
        }

        if ($this->request->getPost('next') === 'copy') {
            return $this->back('"' . $post['name'] . '" opgeslagen. Vul het volgende in.', 'message', site_url('apparaten/nieuw?kopie=' . $id));
        }

        return $this->back('Opgeslagen.', 'message', site_url('klanten/' . $post['customer_id']));
    }

    /**
     * Geeft een geheim terug als JSON (en logt het). Alleen via POST.
     */
    public function reveal($id)
    {
        $device = model(DeviceModel::class)->find($id);
        $field  = $this->request->getPost('field') === 'secret_notes' ? 'secret_notes_enc' : 'password_enc';

        if ($device === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Niet gevonden', 'csrf' => csrf_hash()]);
        }

        try {
            $value = Vault::decrypt($device[$field]);
        } catch (Throwable $e) {
            log_message('error', 'Kluis: ontsleutelen mislukt voor apparaat ' . $id . ': ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Ontsleutelen mislukt. Klopt de encryption.key in .env nog?',
                'csrf'  => csrf_hash(),
            ]);
        }

        Vault::log($field === 'password_enc' ? 'wachtwoord bekeken' : 'notitie bekeken', $device);

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON(['value' => $value, 'csrf' => csrf_hash()]);
    }

    public function delete($id)
    {
        $device = $this->findOr404(DeviceModel::class, $id);
        model(DeviceModel::class)->delete($id);
        Vault::log('verwijderd: ' . mb_substr($device['name'], 0, 18), ['id' => null, 'customer_id' => $device['customer_id']]);

        return $this->back('Apparaat verwijderd.', 'message', site_url('klanten/' . $device['customer_id']));
    }

    /** "Camera 3" -> "Camera 4", "Bewegingsmelder" -> "Bewegingsmelder 2" */
    private function nextName(string $name): string
    {
        if (preg_match('/^(.*?)(\d+)(\D*)$/u', $name, $m)) {
            return $m[1] . ((int) $m[2] + 1) . $m[3];
        }

        return $name . ' 2';
    }

    /** 192.168.1.64 -> 192.168.1.65 (alleen voor IPv4) */
    private function nextIp(?string $ip): ?string
    {
        if ($ip === null || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
        $parts = explode('.', $ip);
        if ((int) $parts[3] >= 254) {
            return $ip;
        }
        $parts[3] = (string) ((int) $parts[3] + 1);

        return implode('.', $parts);
    }
}
