@include('reports.pdf.partials.header')

<p style="font-size:8.5pt;color:#555;">Total lines: {{ number_format($totals['total_lines']) }} &nbsp;|&nbsp; Estimated stock value: {{ $totals['total_value'] }}</p>

<table class="data">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Product</th>
            <th>Category</th>
            <th>Location</th>
            <th class="text-right">Qty</th>
            <th>Unit</th>
            <th class="text-right">Reorder</th>
            <th class="text-right">Price</th>
            <th class="text-right">Stock Value</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($levels as $level)
            <tr>
                <td style="font-family:monospace;font-size:7.5pt;">{{ $level->sku }}</td>
                <td>{{ $level->product_name }}</td>
                <td>{{ $level->category_name ?? '—' }}</td>
                <td>{{ $level->location_name }}</td>
                <td class="text-right">{{ $level->quantity }}</td>
                <td>{{ $level->unit_abbreviation }}</td>
                <td class="text-right">{{ $level->reorder_level }}</td>
                <td class="text-right">{{ number_format($level->selling_price, 2) }}</td>
                <td class="text-right">{{ number_format($level->stock_value, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:#999;">No stock records.</td></tr>
        @endforelse
    </tbody>
</table>

@include('reports.pdf.partials.footer')
