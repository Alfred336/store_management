@include('reports.pdf.partials.header')

<table class="data">
    <thead>
        <tr>
            <th>Customer</th>
            <th>Code</th>
            <th class="text-right">Current (0–30d)</th>
            <th class="text-right">31–60 days</th>
            <th class="text-right">61–90 days</th>
            <th class="text-right">90+ days</th>
            <th class="text-right">Total Outstanding</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row->customer_name }}</td>
                <td>{{ $row->customer_code }}</td>
                <td class="text-right">{{ number_format($row->current_amount, 2) }}</td>
                <td class="text-right">{{ number_format($row->days_31_60, 2) }}</td>
                <td class="text-right">{{ number_format($row->days_61_90, 2) }}</td>
                <td class="text-right">{{ number_format($row->days_90_plus, 2) }}</td>
                <td class="text-right">{{ number_format($row->total_outstanding, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:#999;">No outstanding receivables.</td></tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="text-right">{{ $totals['current'] }}</td>
                <td class="text-right">{{ $totals['days_31_60'] }}</td>
                <td class="text-right">{{ $totals['days_61_90'] }}</td>
                <td class="text-right">{{ $totals['days_90_plus'] }}</td>
                <td class="text-right">{{ $totals['total'] }}</td>
            </tr>
        @endif
    </tbody>
</table>

@include('reports.pdf.partials.footer')
