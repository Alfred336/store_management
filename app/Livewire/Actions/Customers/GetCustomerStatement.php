<?php

namespace App\Livewire\Actions\Customers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Customers\CustomerBalance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/** Build a customer statement without storing a denormalized balance. */
class GetCustomerStatement
{
    public function __construct(
        private readonly CustomerBalance $customerBalance,
    ) {}

    /**
     * @return array{balance: string, total_spent: string, last_purchase_date: string|null, invoices: Collection<int, array<string, mixed>>, payments: Collection<int, array{id: int, number: string, amount: string, method: string, reference: string|null, received_at: string, status: string}>}
     */
    public function __invoke(Customer $customer, ?int $limit = 10): array
    {
        Gate::authorize('view', $customer);

        $summary = $this->customerBalance->summary($customer);

        return [
            'balance' => $summary['balance'],
            'total_spent' => $summary['total_spent'],
            'last_purchase_date' => $summary['last_purchase_date'],
            'invoices' => $this->recentInvoices($customer, $limit),
            'payments' => $this->recentPayments($customer, $limit),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function recentInvoices(Customer $customer, ?int $limit): Collection
    {
        $query = $customer->invoices()
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var Collection<int, array<string, mixed>> $result */
        $result = $query->get()->map(fn (Invoice $invoice): array => [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'issue_date' => $invoice->issue_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'status' => $invoice->status->value,
            'total' => (string) $invoice->total,
            'amount_paid' => (string) $invoice->amount_paid,
            'created_at' => (string) $invoice->created_at,
        ]);

        return $result;
    }

    /** @return Collection<int, array{id: int, number: string, amount: string, method: string, reference: string|null, received_at: string, status: string}> */
    private function recentPayments(Customer $customer, ?int $limit): Collection
    {
        $query = $customer->payments()
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Payment $payment): array => [
            'id' => $payment->id,
            'number' => $payment->number,
            'amount' => (string) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'received_at' => $payment->received_at->format('Y-m-d'),
            'status' => $payment->status,
        ]);
    }
}
