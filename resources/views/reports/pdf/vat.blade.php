@include('reports.pdf.partials.header')

<table class="data">
    <thead>
        <tr>
            <th>Date</th>
            <th class="text-right">Taxable Amount</th>
            <th class="text-right">Exempt Amount</th>
            <th class="text-right">VAT</th>
            <th class="text-right">Gross Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row->period)->format('d M Y') }}</td>
                <td class="text-right">{{ number_format($row->taxable_amount, 2) }}</td>
                <td class="text-right">{{ number_format($row->exempt_amount, 2) }}</td>
                <td class="text-right">{{ number_format($row->vat_amount, 2) }}</td>
                <td class="text-right">{{ number_format($row->gross_total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:#999;">No data.</td></tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr class="total">
                <td>Total</td>
                <td class="text-right">{{ $totals['taxable_amount'] }}</td>
                <td class="text-right">{{ $totals['exempt_amount'] }}</td>
                <td class="text-right">{{ $totals['vat_amount'] }}</td>
                <td class="text-right">{{ $totals['gross_total'] }}</td>
            </tr>
        @endif
    </tbody>
</table>

@include('reports.pdf.partials.footer')
