<?php

namespace App\Livewire\Actions\Customers;

use App\Models\Customer;
use App\Services\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/** Build a customer statement without storing a denormalized balance. */
class GetCustomerStatement
{
    /**
     * @return array{balance: string, total_spent: string, last_purchase_date: string|null, invoices: Collection<int, array<array-key, mixed>>, payments: Collection<int, array<array-key, mixed>>}
     */
    public function __invoke(Customer $customer, ?int $limit = 10): array
    {
        Gate::authorize('view', $customer);

        $invoices = $this->recentInvoices($customer, $limit);
        $balance = '0.00';
        $totalSpent = '0.00';
        $lastPurchaseDate = null;

        if ($this->invoiceTableIsReady()) {
            $query = DB::table('invoices')->where('customer_id', $customer->id);

            if (Schema::hasColumn('invoices', 'status')) {
                $query->whereIn('status', ['issued', 'partially_paid', 'paid']);
            }

            $dateColumn = Schema::hasColumn('invoices', 'issue_date') ? 'issue_date' : null;

            foreach ($query->get(['total', 'amount_paid', ...($dateColumn === null ? [] : [$dateColumn])]) as $invoice) {
                $totalSpent = app(Money::class)->add($totalSpent, (string) $invoice->total);
                $outstanding = app(Money::class)->sub((string) $invoice->total, (string) $invoice->amount_paid);
                $balance = app(Money::class)->add($balance, $outstanding);

                if ($dateColumn !== null && $invoice->{$dateColumn} !== null
                    && ($lastPurchaseDate === null || $invoice->{$dateColumn} > $lastPurchaseDate)) {
                    $lastPurchaseDate = (string) $invoice->{$dateColumn};
                }
            }
        }

        return [
            'balance' => $balance,
            'total_spent' => $totalSpent,
            'last_purchase_date' => $lastPurchaseDate,
            'invoices' => $invoices,
            'payments' => $this->recentPayments($customer, $limit),
        ];
    }

    /** @return Collection<int, array<array-key, mixed>> */
    private function recentInvoices(Customer $customer, ?int $limit): Collection
    {
        if (! $this->invoiceTableIsReady()) {
            return collect();
        }

        $columns = array_values(array_filter(
            ['number', 'issue_date', 'due_date', 'status', 'total', 'amount_paid', 'created_at'],
            fn (string $column): bool => Schema::hasColumn('invoices', $column),
        ));
        $sortColumn = Schema::hasColumn('invoices', 'issue_date') ? 'issue_date' : 'id';

        $query = DB::table('invoices')
            ->where('customer_id', $customer->id)
            ->orderByDesc($sortColumn)
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get($columns)->map(fn (object $invoice): array => (array) $invoice);
    }

    /** @return Collection<int, array<array-key, mixed>> */
    private function recentPayments(Customer $customer, ?int $limit): Collection
    {
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

    private function invoiceTableIsReady(): bool
    {
        return Schema::hasTable('invoices')
            && Schema::hasColumn('invoices', 'customer_id')
            && Schema::hasColumn('invoices', 'total')
            && Schema::hasColumn('invoices', 'amount_paid');
    }
}
