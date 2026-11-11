<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Kleine client voor de Moneybird API v2.
 *
 * Instellingen in .env:
 *   moneybird.token, moneybird.administrationId, moneybird.taxRateId (optioneel)
 */
class Moneybird
{
    private string $token;
    private string $administrationId;

    public function __construct(?string $token = null, ?string $administrationId = null)
    {
        $this->token            = (string) ($token ?? env('moneybird.token', ''));
        $this->administrationId = (string) ($administrationId ?? env('moneybird.administrationId', ''));
    }

    public function configured(): bool
    {
        return $this->token !== '' && $this->administrationId !== '';
    }

    public static function defaultTaxRateId(): ?string
    {
        $id = (string) env('moneybird.taxRateId', '');

        return $id === '' ? null : $id;
    }

    /** Link naar een factuur in Moneybird */
    public function invoiceUrl(string $invoiceId): string
    {
        return 'https://moneybird.com/' . rawurlencode($this->administrationId) . '/sales_invoices/' . rawurlencode($invoiceId);
    }

    public function contactUrl(string $contactId): string
    {
        return 'https://moneybird.com/' . rawurlencode($this->administrationId) . '/contacts/' . rawurlencode($contactId);
    }

    /** @return list<array> alle contacten (alle pagina's) */
    public function contacts(): array
    {
        $all = [];
        for ($page = 1; $page <= 100; $page++) {
            $batch = $this->request('GET', 'contacts', ['page' => $page, 'per_page' => 100]);
            if ($batch === []) {
                break;
            }
            array_push($all, ...$batch);
            if (count($batch) < 100) {
                break;
            }
        }

        return $all;
    }

    public function taxRates(): array
    {
        return $this->request('GET', 'tax_rates', ['filter' => 'tax_rate_type:sales_invoice']);
    }

    /** Alle grootboekrekeningen ("categorieën" in Moneybird) */
    public function ledgerAccounts(): array
    {
        return $this->request('GET', 'ledger_accounts');
    }

    /**
     * Inkoopfacturen en bonnetjes in een periode (alle pagina's).
     *
     * @param string $from Y-m-d
     * @param string $to   Y-m-d
     *
     * @return list<array>
     */
    public function purchaseDocuments(string $from, string $to): array
    {
        $period = str_replace('-', '', $from) . '..' . str_replace('-', '', $to);
        $all    = [];

        foreach (['documents/purchase_invoices', 'documents/receipts'] as $path) {
            for ($page = 1; $page <= 200; $page++) {
                $batch = $this->request('GET', $path, ['filter' => 'period:' . $period, 'page' => $page, 'per_page' => 100]);
                if ($batch === []) {
                    break;
                }
                foreach ($batch as $doc) {
                    $doc['_kind'] = $path === 'documents/receipts' ? 'bonnetje' : 'inkoopfactuur';
                    $all[] = $doc;
                }
                if (count($batch) < 100) {
                    break;
                }
            }
        }

        return $all;
    }

    public function products(): array
    {
        return $this->request('GET', 'products', ['per_page' => 100]);
    }

    /**
     * Maakt een conceptfactuur aan.
     *
     * @param list<array{description:string, price:float|string, amount:float|string, period?:string, product_id?:string, tax_rate_id?:string}> $lines
     */
    public function createDraftInvoice(string $contactId, array $lines, ?string $reference = null): array
    {
        $details = [];
        foreach ($lines as $line) {
            $details[] = array_filter([
                'description' => $line['description'],
                'price'       => number_format((float) $line['price'], 2, '.', ''),
                'amount'      => rtrim(rtrim(number_format((float) $line['amount'], 2, '.', ''), '0'), '.'),
                'period'      => $line['period'] ?? null,
                'product_id'  => $line['product_id'] ?? null,
                'tax_rate_id' => $line['tax_rate_id'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $this->request('POST', 'sales_invoices', null, [
            'sales_invoice' => array_filter([
                'contact_id'          => $contactId,
                'reference'           => $reference,
                'prices_are_incl_tax' => false,
                'details_attributes'  => $details,
            ], static fn ($v) => $v !== null),
        ]);
    }

    private function request(string $method, string $path, ?array $query = null, ?array $body = null): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Moneybird is niet ingesteld (moneybird.token en moneybird.administrationId in .env).');
        }

        $url = 'https://moneybird.com/api/v2/' . rawurlencode($this->administrationId) . '/' . $path . '.json';

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
            ],
            'http_errors' => false,
            'timeout'     => 20,
        ];
        if ($query !== null) {
            $options['query'] = $query;
        }
        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = \Config\Services::curlrequest([], null, null, false)->request($method, $url, $options);
        $status   = $response->getStatusCode();
        $data     = json_decode((string) $response->getBody(), true);

        if ($status >= 400) {
            $error = is_array($data) ? ($data['error'] ?? $data['message'] ?? json_encode($data)) : (string) $response->getBody();
            if (is_array($error)) {
                $error = json_encode($error, JSON_UNESCAPED_UNICODE);
            }

            throw new RuntimeException('Moneybird gaf een fout (' . $status . '): ' . $error);
        }

        return is_array($data) ? $data : [];
    }
}
