@include('reports.pdf.partials.header')

<table class="data">
    <thead>
        <tr>
            <th>Method</th>
            <th class="text-right">Transactions</th>
            <th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ ucwords(str_replace('_', ' ', $row->method)) }}</td>
                <td class="text-right">{{ number_format($row->payment_count) }}</td>
                <td class="text-right">{{ number_format($row->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="text-align:center;color:#999;">No data.</td></tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr class="total">
                <td>Total</td>
                <td class="text-right">{{ number_format($totals['payment_count']) }}</td>
                <td class="text-right">{{ $totals['amount'] }}</td>
            </tr>
        @endif
    </tbody>
</table>

@include('reports.pdf.partials.footer')
