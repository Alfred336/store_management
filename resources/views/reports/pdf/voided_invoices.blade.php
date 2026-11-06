@include('reports.pdf.partials.header')

<table class="data">
    <thead>
        <tr>
            <th>Number</th>
            <th>Customer</th>
            <th>Location</th>
            <th>Ch.</th>
            <th class="text-right">Total</th>
            <th>Issue Date</th>
            <th>Voided At</th>
            <th>Voided By</th>
            <th>Reason</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoices as $invoice)
            <tr>
                <td style="font-family:monospace;font-size:7.5pt;">{{ $invoice->number }}</td>
                <td>{{ $invoice->customer_name }}</td>
                <td>{{ $invoice->location_name }}</td>
                <td>{{ strtoupper($invoice->channel) }}</td>
                <td class="text-right">{{ number_format($invoice->total, 2) }}</td>
                <td>{{ $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->format('d M Y') : '—' }}</td>
                <td>{{ \Carbon\Carbon::parse($invoice->voided_at)->setTimezone('Africa/Dar_es_Salaam')->format('d M Y H:i') }}</td>
                <td>{{ $invoice->voided_by_name ?? '—' }}</td>
                <td style="max-width:120pt;word-wrap:break-word;">{{ $invoice->void_reason ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:#999;">No voided invoices.</td></tr>
        @endforelse
    </tbody>
</table>

@include('reports.pdf.partials.footer')
