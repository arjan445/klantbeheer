<?php

namespace App\Controllers;

use App\Libraries\Network;
use App\Libraries\Topology;
use App\Models\NetworkDeviceModel;
use App\Models\NetworkSiteModel;
use App\Models\SiteModel;
use Throwable;

/**
 * Topologie van een netwerksite: tekenen, en verbindingen zelf corrigeren.
 */
class Topologies extends BaseController
{
    public function show($id)
    {
        $site = $this->findOr404(NetworkSiteModel::class, $id);
        $loc  = $site['site_id'] ? model(SiteModel::class)->select('sites.*, customers.name AS customer_name')
            ->join('customers', 'customers.id = sites.customer_id')->where('sites.id', $site['site_id'])->first() : null;

        // Andere topologieën om naar te springen
        $others = model(NetworkSiteModel::class)->select('id, platform, name')->where('ignored', 0)
            ->notLike('external_id', 'host:', 'after')->orderBy('name')->findAll();

        return $this->page('network/topology', [
            'site'    => $site,
            'loc'     => $loc,
            'nodes'   => Topology::nodes((int) $site['id']),
            'addable' => user_can('vault.manage') ? Topology::addable((int) $site['id']) : [],
            'types'   => Topology::TYPES,
            'others'  => $others,
            'manage'  => user_can('vault.manage'),
            'lastAt'  => (int) Network::setting('topology.at.' . $site['id']) ?: null,
        ], 'Topologie · ' . $site['name']);
    }

    /** Verbinding zelf instellen (vast) */
    public function save($id)
    {
        $site = $this->findOr404(NetworkSiteModel::class, $id);
        $post = $this->request->getPost(['child_id', 'parent_id', 'parent_port', 'local_port', 'link_type', 'note']);
        $child = (int) $post['child_id'];
        $parent = (int) $post['parent_id'] ?: null;
        if (! $this->inView($child, (int) $site['id']) || ($parent !== null && ! $this->inView($parent, (int) $site['id']))) {
            return $this->json(['error' => 'Apparaat hoort niet bij deze topologie.'], 422);
        }
        try {
            Topology::setParent($child, $parent, $post['parent_port'], $post['local_port'], (string) $post['link_type'], $post['note'], (int) $site['id']);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json(['nodes' => Topology::nodes((int) $site['id'])]);
    }

    /** Terug naar automatisch */
    public function auto($id)
    {
        $site  = $this->findOr404(NetworkSiteModel::class, $id);
        $child = (int) $this->request->getPost('child_id');
        if (! $this->inView($child, (int) $site['id'])) {
            return $this->json(['error' => 'Apparaat hoort niet bij deze topologie.'], 422);
        }
        Topology::unlock($child);

        return $this->json(['nodes' => Topology::nodes((int) $site['id'])]);
    }

    /** Apparaat uit een andere site (bv. UISP-straalverbinding) toevoegen */
    public function add($id)
    {
        $site  = $this->findOr404(NetworkSiteModel::class, $id);
        $child = (int) $this->request->getPost('child_id');
        if (model(NetworkDeviceModel::class)->find($child) === null) {
            return $this->json(['error' => 'Apparaat niet gevonden.'], 422);
        }
        Topology::addToView($child, (int) $site['id']);

        return $this->json(['nodes' => Topology::nodes((int) $site['id']), 'added' => $child]);
    }

    /** UniFi-verbindingen nu opnieuw ophalen */
    public function refresh($id)
    {
        $site = $this->findOr404(NetworkSiteModel::class, $id);
        @set_time_limit(120);
        $notes = $site['platform'] === 'unifi' ? Topology::refreshUnifi((int) $site['id']) : [];

        return $this->json(['nodes' => Topology::nodes((int) $site['id']), 'notes' => $notes]);
    }

    private function inView(int $networkDeviceId, int $siteId): bool
    {
        return in_array($networkDeviceId, array_column(Topology::nodes($siteId), 'id'), true);
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store')->setJSON($data + ['csrf' => csrf_hash()]);
    }
}
