<?php

namespace App\Libraries;

use App\Models\CustomerModel;
use App\Models\InvoiceLineModel;
use App\Models\SubscriptionModel;
use DateTimeImmutable;
use RuntimeException;

/**
 * Facturatie van abonnementen.
 *
 * Een abonnement wordt vooraf gefactureerd: de periode loopt van
 * next_invoice_date tot en met (next_invoice_date + interval - 1 dag).
 * Na het factureren schuift next_invoice_date één interval op.
 */
class Billing
{
    public static function daysAhead(): int
    {
        return max(0, (int) env('billing.daysAhead', 30));
    }

    /**
     * Abonnementen die binnen $days dagen (of eerder) gefactureerd moeten worden.
     */
    public static function due(?int $days = null, ?int $customerId = null): array
    {
        $until = date('Y-m-d', strtotime('+' . ($days ?? self::daysAhead()) . ' days'));

        $model = model(SubscriptionModel::class)->withDetails()
            ->where('subscriptions.status', 'active')
            ->where('subscriptions.next_invoice_date <=', $until)
            ->groupStart()
                ->where('subscriptions.end_date', null)
                ->orWhere('subscriptions.end_date >= subscriptions.next_invoice_date', null, false)
            ->groupEnd()
            ->orderBy('customers.name')->orderBy('subscriptions.next_invoice_date');

        if ($customerId !== null) {
            $model->where('subscriptions.customer_id', $customerId);
        }

        return $model->findAll();
    }

    /** @return array{0:string,1:string} periode [start, eind] */
    public static function period(array $sub): array
    {
        $start = new DateTimeImmutable($sub['next_invoice_date']);
        $end   = self::addMonths($start, (int) $sub['interval_months'])->modify('-1 day');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    /**
     * Maanden optellen zonder overloop: 31 januari + 1 maand = 28/29 februari
     * (PHP zou 3 maart geven).
     */
    public static function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $first  = $date->modify('first day of this month')->modify('+' . $months . ' months');
        $day    = min((int) $date->format('j'), (int) $first->format('t'));

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
    }

    public static function lineDescription(array $sub): string
    {
        [$start, $end] = self::period($sub);
        $text = $sub['service_name'];
        if (! empty($sub['label'])) {
            $text .= ' - ' . $sub['label'];
        }

        return $text . ' (' . datum($start) . ' t/m ' . datum($end) . ')';
    }

    /** Jaarbedrag van een abonnement (verkoop of inkoop) */
    public static function yearly(array $sub, string $priceField = 'eff_price'): float
    {
        $months = max(1, (int) $sub['interval_months']);

        return (float) $sub[$priceField] * (float) $sub['quantity'] * 12 / $months;
    }

    /**
     * Factureert de gegeven abonnementen van één klant.
     *
     * @param list<int> $subscriptionIds
     * @param bool      $viaMoneybird  false = alleen als gefactureerd markeren (handmatig)
     *
     * @return array{count:int, total:float, invoice_id:?string}
     */
    public static function invoice(int $customerId, array $subscriptionIds, bool $viaMoneybird = true): array
    {
        $customer = model(CustomerModel::class)->find($customerId);
        if ($customer === null) {
            throw new RuntimeException('Klant niet gevonden.');
        }

        $subs = model(SubscriptionModel::class)->withDetails()
            ->where('subscriptions.customer_id', $customerId)
            ->whereIn('subscriptions.id', $subscriptionIds ?: [0])
            ->where('subscriptions.status', 'active')
            ->findAll();

        if ($subs === []) {
            throw new RuntimeException('Geen (actieve) abonnementen geselecteerd.');
        }

        $lines = [];
        $total = 0.0;
        foreach ($subs as $sub) {
            [$start, $end] = self::period($sub);
            $lines[] = [
                'sub'         => $sub,
                'start'       => $start,
                'end'         => $end,
                'description' => self::lineDescription($sub),
                'price'       => (float) $sub['eff_price'],
                'amount'      => (float) $sub['quantity'],
                'period'      => str_replace('-', '', $start) . '..' . str_replace('-', '', $end),
                'product_id'  => $sub['moneybird_product_id'] ?: null,
                'tax_rate_id' => $sub['tax_rate_id'] ?: Moneybird::defaultTaxRateId(),
            ];
            $total += (float) $sub['eff_price'] * (float) $sub['quantity'];
        }

        $invoiceId = null;
        if ($viaMoneybird) {
            if (empty($customer['moneybird_contact_id'])) {
                throw new RuntimeException('Deze klant is nog niet gekoppeld aan een Moneybird-contact.');
            }
            $invoice   = (new Moneybird())->createDraftInvoice($customer['moneybird_contact_id'], $lines, 'Abonnementen ' . date('Y'));
            $invoiceId = (string) ($invoice['id'] ?? '');
        }

        // Pas na een geslaagde Moneybird-aanroep vastleggen en doorschuiven
        $db = db_connect();
        $db->transStart();
        foreach ($lines as $line) {
            $sub = $line['sub'];
            model(InvoiceLineModel::class)->insert([
                'subscription_id'      => $sub['id'],
                'customer_id'          => $customerId,
                'description'          => $line['description'],
                'period_start'         => $line['start'],
                'period_end'           => $line['end'],
                'quantity'             => $line['amount'],
                'price'                => $line['price'],
                'total'                => round($line['price'] * $line['amount'], 2),
                'method'               => $viaMoneybird ? 'moneybird' : 'manual',
                'moneybird_invoice_id' => $invoiceId,
                'user_id'              => auth()->id(),
            ]);
            model(SubscriptionModel::class)->update($sub['id'], [
                'next_invoice_date' => date('Y-m-d', strtotime($line['end'] . ' +1 day')),
            ]);
        }
        $db->transComplete();

        return ['count' => count($lines), 'total' => $total, 'invoice_id' => $invoiceId];
    }
}
