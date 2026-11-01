<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Customer statement — {{ $customer->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 11px; }
        h1, h2 { margin: 0 0 8px; }
        .muted { color: #666; }
        .summary { margin: 18px 0; padding: 12px; background: #f3f4f6; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0 20px; }
        th, td { padding: 7px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f3f4f6; }
        .amount { text-align: right; }
    </style>
</head>
<body>
    <h1>Customer statement</h1>
    <p class="muted">{{ $customer->name }} · {{ $customer->code }} · Generated {{ now()->format('Y-m-d H:i') }}</p>
    <div class="summary">
        <strong>Outstanding balance:</strong> {{ $statement['balance'] }} {{ app(\App\Services\Settings\Settings::class)->get('business.currency', 'TZS') }}<br>
        <strong>Total spent:</strong> {{ $statement['total_spent'] }} {{ app(\App\Services\Settings\Settings::class)->get('business.currency', 'TZS') }}<br>
        <strong>Last purchase:</strong> {{ $statement['last_purchase_date'] ?: '—' }}
    </div>

    <h2>Invoices</h2>
    <table><thead><tr><th>Number</th><th>Date</th><th>Status</th><th class="amount">Total</th><th class="amount">Paid</th></tr></thead><tbody>
        @forelse ($statement['invoices'] as $invoice)
            <tr><td>{{ $invoice['number'] ?? '—' }}</td><td>{{ $invoice['issue_date'] ?? $invoice['created_at'] ?? '—' }}</td><td>{{ $invoice['status'] ?? '—' }}</td><td class="amount">{{ $invoice['total'] ?? '0.00' }}</td><td class="amount">{{ $invoice['amount_paid'] ?? '0.00' }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">No invoices recorded.</td></tr>
        @endforelse
    </tbody></table>

    <h2>Payments</h2>
    <table><thead><tr><th>Date</th><th>Reference</th><th>Method</th><th class="amount">Amount</th></tr></thead><tbody>
        @forelse ($statement['payments'] as $payment)
            <tr><td>{{ $payment['paid_at'] ?? $payment['payment_date'] ?? $payment['created_at'] ?? '—' }}</td><td>{{ $payment['reference'] ?? $payment['number'] ?? '—' }}</td><td>{{ $payment['method'] ?? $payment['payment_method'] ?? '—' }}</td><td class="amount">{{ $payment['amount'] ?? '0.00' }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No payments recorded.</td></tr>
        @endforelse
    </tbody></table>
</body>
</html>