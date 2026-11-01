<?php

namespace App\Livewire\Actions\Customers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Customers\CustomerBalance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/** Build a customer statement without storing a denormalized balance. */
class GetCustomerStatement
{
    public function __construct(
        private readonly CustomerBalance $customerBalance,
    ) {}

    /**
     * @return array{balance: string, total_spent: string, last_purchase_date: string|null, invoices: Collection<int, array<string, mixed>>, payments: Collection<int, array<array-key, mixed>>}
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

    /** @return Collection<int, array<array-key, mixed>> */
    private function recentPayments(Customer $customer, ?int $limit): Collection
    {
        // removed in Phase 4 when payments exist
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'customer_id')
            || ! Schema::hasColumn('payments', 'amount')) {
            return collect();
        }

        $dateColumn = collect(['paid_at', 'payment_date', 'created_at'])
            ->first(fn (string $column): bool => Schema::hasColumn('payments', $column));
        $optionalColumns = ['reference', 'method', 'payment_method', 'number'];
        $columns = array_values(array_filter(
            ['amount', ...$optionalColumns, ...($dateColumn === null ? [] : [$dateColumn])],
            fn (string $column): bool => Schema::hasColumn('payments', $column),
        ));
        $query = DB::table('payments')->where('customer_id', $customer->id);

        if ($dateColumn !== null) {
            $query->orderByDesc($dateColumn);
        }

        $query->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get($columns)->map(fn (object $payment): array => (array) $payment);
    }
}
