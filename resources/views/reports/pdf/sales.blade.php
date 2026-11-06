@include('reports.pdf.partials.header')

<table class="data">
    <thead>
        <tr>
            <th>Period</th>
            <th class="text-right">Invoices</th>
            <th class="text-right">Subtotal</th>
            <th class="text-right">Discounts</th>
            <th class="text-right">VAT</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $group_by === 'day' ? \Carbon\Carbon::parse($row->period)->format('d M Y') : \Carbon\Carbon::parse($row->period)->format('M Y') }}</td>
                <td class="text-right">{{ number_format($row->invoice_count) }}</td>
                <td class="text-right">{{ number_format($row->subtotal, 2) }}</td>
                <td class="text-right">{{ number_format($row->discount_total, 2) }}</td>
                <td class="text-right">{{ number_format($row->tax_total, 2) }}</td>
                <td class="text-right">{{ number_format($row->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#999;">No data.</td></tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr class="total">
                <td>Total</td>
                <td></td>
                <td class="text-right">{{ $totals['subtotal'] }}</td>
                <td class="text-right">{{ $totals['discount_total'] }}</td>
                <td class="text-right">{{ $totals['tax_total'] }}</td>
                <td class="text-right">{{ $totals['total'] }}</td>
            </tr>
        @endif
    </tbody>
</table>

@include('reports.pdf.partials.footer')
