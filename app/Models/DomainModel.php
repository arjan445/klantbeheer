<?php

namespace App\Models;

use CodeIgniter\Model;

class DomainModel extends Model
{
    protected $table         = 'domains';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['customer_id', 'name', 'registrar', 'notes'];

    /** "https://www.Klant.nl/" -> "klant.nl" */
    public static function normalize(?string $name): string
    {
        $name = strtolower(trim((string) $name));
        $name = preg_replace('#^https?://#', '', $name);
        $name = preg_replace('#^www\.#', '', $name);

        return rtrim(explode('/', $name)[0], '.');
    }

    public static function isValid(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $name);
    }

    /**
     * Zoekt het domein op of maakt het aan voor deze klant.
     * Bestaat het al bij een andere klant, dan wordt dat domein teruggegeven.
     */
    public function findOrCreate(string $name, int $customerId): ?array
    {
        $name = self::normalize($name);
        if (! self::isValid($name)) {
            return null;
        }
        $domain = $this->where('name', $name)->first();
        if ($domain === null) {
            $id     = $this->insert(['customer_id' => $customerId, 'name' => $name]);
            $domain = $this->find($id);
        }

        return $domain;
    }
}
