<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseInvoiceModel extends Model
{
    protected $table         = 'purchase_invoices';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['supplier_id', 'supplier', 'number', 'date', 'amount', 'moneybird_id', 'notes'];

    /** Met de naam van de leverancier (uit de leverancierslijst of de vrije naam) */
    public function withSupplier(): self
    {
        return $this->select('purchase_invoices.*, COALESCE(suppliers.name, purchase_invoices.supplier) AS supplier_name')
            ->join('suppliers', 'suppliers.id = purchase_invoices.supplier_id', 'left');
    }

    /** Korte omschrijving: "Wimood 12345 · 03-11-2026" */
    public static function label(array $inv): string
    {
        return trim(($inv['supplier_name'] ?? $inv['supplier'] ?? '') . ' ' . $inv['number']) . ($inv['date'] ? ' · ' . datum($inv['date']) : '');
    }

    /** Recentste eerst, voor keuzelijsten */
    public function options(int $limit = 200): array
    {
        return $this->withSupplier()->orderBy('purchase_invoices.date', 'DESC')->orderBy('purchase_invoices.id', 'DESC')->findAll($limit);
    }
}
