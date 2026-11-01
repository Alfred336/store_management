<?php

namespace App\Services\Customers;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Services\Money\Money;

/**
 * Pure balance computation for a customer.
 *
 * This class has NO authorization dependency and can be called from any
 * context — credit-limit checks, invoice issuance, statement display, etc.
 *
 * Only posted invoices (Issued, PartiallyPaid, Paid) contribute to the
 * outstanding balance.  Drafts and Void invoices are excluded.
 */
class CustomerBalance
{
    public function __construct(
        private readonly Money $money,
    ) {}

    /**
     * Return the outstanding balance (total − amount_paid) for posted invoices.
     *
     * @return numeric-string
     */
    public function outstanding(Customer $customer): string
    {
        $postedStatuses = [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
        ];

        $rows = $customer->invoices()
            ->whereIn('status', $postedStatuses)
            ->get(['total', 'amount_paid']);

        $balance = '0.00';

        foreach ($rows as $invoice) {
            $outstanding = $this->money->sub(
                (string) $invoice->total,
                (string) $invoice->amount_paid,
            );
            $balance = $this->money->add($balance, $outstanding);
        }

        return $balance;
    }

    /**
     * Return both the outstanding balance and the total amount invoiced
     * (across all posted invoices).
     *
     * @return array{balance: numeric-string, total_spent: numeric-string, last_purchase_date: string|null}
     */
    public function summary(Customer $customer): array
    {
        $postedStatuses = [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
        ];

        $rows = $customer->invoices()
            ->whereIn('status', $postedStatuses)
            ->get(['total', 'amount_paid', 'issue_date']);

        $balance = '0.00';
        $totalSpent = '0.00';
        $lastPurchaseDate = null;

        foreach ($rows as $invoice) {
            $totalSpent = $this->money->add($totalSpent, (string) $invoice->total);
            $outstanding = $this->money->sub(
                (string) $invoice->total,
                (string) $invoice->amount_paid,
            );
            $balance = $this->money->add($balance, $outstanding);

            $dateValue = $invoice->issue_date?->format('Y-m-d');
            if ($dateValue !== null && ($lastPurchaseDate === null || $dateValue > $lastPurchaseDate)) {
                $lastPurchaseDate = $dateValue;
            }
        }

        return [
            'balance' => $balance,
            'total_spent' => $totalSpent,
            'last_purchase_date' => $lastPurchaseDate,
        ];
    }
}
