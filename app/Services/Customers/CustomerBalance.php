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
     * Return the net outstanding balance:
     *   sum(invoice.total - invoice.amount_paid) for open invoices (issued, partially_paid)
     *   minus unallocated credit from active payments.
     *
     * The result may be negative when the customer has pre-paid more than they owe.
     *
     * @return numeric-string
     */
    public function outstanding(Customer $customer): string
    {
        $openStatuses = [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid];

        $rows = $customer->invoices()
            ->whereIn('status', $openStatuses)
            ->get(['total', 'amount_paid']);

        $balance = '0.00';
        foreach ($rows as $invoice) {
            $balance = $this->money->add(
                $balance,
                $this->money->sub((string) $invoice->total, (string) $invoice->amount_paid),
            );
        }

        // Subtract unallocated credit from active payments
        $unallocated = $this->unallocatedCredit($customer);
        if ($this->money->compare($unallocated, '0.00') > 0) {
            $balance = $this->money->sub($balance, $unallocated);
        }

        return $balance;
    }

    /**
     * Sum of unallocated credit across all active payments for this customer.
     *
     * @return numeric-string
     */
    public function unallocatedCredit(Customer $customer): string
    {
        $payments = $customer->payments()
            ->where('status', 'active')
            ->withSum('allocations', 'amount')
            ->get(['id', 'amount']);

        $credit = '0.00';
        foreach ($payments as $payment) {
            $allocated = number_format((float) ($payment->allocations_sum_amount ?? 0), 2, '.', '');
            $remaining = $this->money->sub((string) $payment->amount, $allocated);
            if ($this->money->compare($remaining, '0.00') > 0) {
                $credit = $this->money->add($credit, $remaining);
            }
        }

        return $credit;
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

        $totalSpent = '0.00';
        $lastPurchaseDate = null;

        foreach ($rows as $invoice) {
            $totalSpent = $this->money->add($totalSpent, (string) $invoice->total);

            $dateValue = $invoice->issue_date?->format('Y-m-d');
            if ($dateValue !== null && ($lastPurchaseDate === null || $dateValue > $lastPurchaseDate)) {
                $lastPurchaseDate = $dateValue;
            }
        }

        return [
            'balance' => $this->outstanding($customer),
            'total_spent' => $totalSpent,
            'last_purchase_date' => $lastPurchaseDate,
        ];
    }
}
