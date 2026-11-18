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

    public function estimateUrl(string $estimateId): string
    {
        return 'https://moneybird.com/' . rawurlencode($this->administrationId) . '/estimates/' . rawurlencode($estimateId);
    }

    /** Offerteregels in Moneybird-formaat */
    private static function estimateDetails(array $lines): array
    {
        $details = [];
        foreach ($lines as $line) {
            if (! empty($line['text'])) {
                $details[] = ['description' => $line['description']]; // tekstregel / kopje
                continue;
            }
            $details[] = array_filter([
                'description' => $line['description'],
                'price'       => number_format((float) $line['price'], 2, '.', ''),
                'amount'      => rtrim(rtrim(number_format((float) $line['amount'], 2, '.', ''), '0'), '.'),
                'tax_rate_id' => $line['tax_rate_id'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $details;
    }

    /**
     * Maakt een conceptofferte aan.
     *
     * @param list<array{description:string, price?:float|string, amount?:float|string, tax_rate_id?:?string, text?:bool}> $lines
     */
    public function createEstimate(string $contactId, array $lines, ?string $reference = null): array
    {
        return $this->request('POST', 'estimates', null, [
            'estimate' => array_filter([
                'contact_id'          => $contactId,
                'reference'           => $reference,
                'prices_are_incl_tax' => false,
                'details_attributes'  => self::estimateDetails($lines),
            ], static fn ($v) => $v !== null),
        ]);
    }

    /** Offerte ophalen; null als hij in Moneybird niet (meer) bestaat */
    public function getEstimate(string $estimateId): ?array
    {
        try {
            return $this->request('GET', 'estimates/' . rawurlencode($estimateId));
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Werkt een bestaande offerte bij: alle oude regels eruit, de nieuwe erin.
     * Het offertenummer en de link blijven hetzelfde.
     */
    public function updateEstimate(array $existing, string $contactId, array $lines, ?string $reference = null): array
    {
        $details = array_map(static fn ($d) => ['id' => $d['id'], '_destroy' => true], $existing['details'] ?? []);
        array_push($details, ...self::estimateDetails($lines));

        return $this->request('PATCH', 'estimates/' . rawurlencode((string) $existing['id']), null, [
            'estimate' => array_filter([
                'contact_id'          => $contactId,
                'reference'           => $reference,
                'prices_are_incl_tax' => false,
                'details_attributes'  => $details,
            ], static fn ($v) => $v !== null),
        ]);
    }

    /** Offerte in Moneybird verwijderen; true als hij weg is (of al niet meer bestond) */
    public function deleteEstimate(string $estimateId): bool
    {
        try {
            $this->request('DELETE', 'estimates/' . rawurlencode($estimateId));
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        return true;
    }

    // ---------------------------------------------------------------- Uren & memoriaal

    /** @return list<array{id:string,name:string}> */
    public function users(): array
    {
        return array_map(static fn ($u) => ['id' => (string) $u['id'], 'name' => (string) ($u['name'] ?? $u['email'] ?? $u['id'])], $this->request('GET', 'users'));
    }

    /** @return list<array> grootboekrekeningen */
    public function ledgerAccounts(): array
    {
        $list = $this->request('GET', 'ledger_accounts');
        usort($list, static fn ($a, $b) => strcmp((string) ($a['account_id'] ?? $a['name']), (string) ($b['account_id'] ?? $b['name'])));

        return $list;
    }

    /** Uren aanmaken of bijwerken; geeft het Moneybird-id terug */
    public function saveTimeEntry(?string $id, array $entry): string
    {
        if ($id) {
            try {
                return (string) $this->request('PATCH', 'time_entries/' . rawurlencode($id), null, ['time_entry' => $entry])['id'];
            } catch (RuntimeException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
                // in Moneybird verwijderd: opnieuw aanmaken
            }
        }

        return (string) $this->request('POST', 'time_entries', null, ['time_entry' => $entry])['id'];
    }

    public function deleteTimeEntry(string $id): void
    {
        try {
            $this->request('DELETE', 'time_entries/' . rawurlencode($id));
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Memoriaalboeking aanmaken, of de bestaande bijwerken (oude regels weg, nieuwe erin, zelfde boeking).
     *
     * @param list<array{ledger_account_id:string,debit:string,credit:string,description:string}> $entries
     */
    public function saveJournal(?string $id, string $date, string $reference, array $entries): string
    {
        $doc = ['reference' => $reference, 'date' => $date];
        if ($id) {
            try {
                $old = $this->request('GET', 'documents/general_journal_documents/' . rawurlencode($id));
                $attrs = [];
                foreach ($old['general_journal_document_entries'] ?? [] as $e) {
                    $attrs[] = ['id' => $e['id'], '_destroy' => true];
                }
                $doc['general_journal_document_entries_attributes'] = array_merge($attrs, $entries);

                return (string) $this->request('PATCH', 'documents/general_journal_documents/' . rawurlencode($id), null, ['general_journal_document' => $doc])['id'];
            } catch (RuntimeException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }
        $doc['general_journal_document_entries_attributes'] = $entries;

        return (string) $this->request('POST', 'documents/general_journal_documents', null, ['general_journal_document' => $doc])['id'];
    }

    public function deleteJournal(string $id): void
    {
        try {
            $this->request('DELETE', 'documents/general_journal_documents/' . rawurlencode($id));
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    public function journalUrl(string $id): string
    {
        return 'https://moneybird.com/' . $this->administrationId . '/documents/' . rawurlencode($id);
    }

    public function timeEntryUrl(): string
    {
        return 'https://moneybird.com/' . $this->administrationId . '/time_entries';
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

            throw new RuntimeException('Moneybird gaf een fout (' . $status . '): ' . $error, $status);
        }

        return is_array($data) ? $data : [];
    }
}
