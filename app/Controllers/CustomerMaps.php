<?php

namespace App\Controllers;

use App\Libraries\CustomerMap;
use App\Libraries\Network;
use Throwable;

/** Klantenkaart: waar gaat wat mis? (dashboard en volledig scherm) */
class CustomerMaps extends BaseController
{
    public function index()
    {
        return $this->page('network/map', [
            'map'    => CustomerMap::points(),
            'status' => Network::configured() ? Network::status() : null,
        ], 'Klantenkaart');
    }

    /** Actuele punten (de kaart ververst zichzelf) */
    public function data()
    {
        return $this->json(CustomerMap::points());
    }

    /** Adressen zonder coördinaten omzetten, een paar tegelijk */
    public function geocode()
    {
        try {
            $r = CustomerMap::fill(15, 20);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }

        return $this->json($r + CustomerMap::points());
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store')->setJSON($data + ['csrf' => csrf_hash()]);
    }
}
