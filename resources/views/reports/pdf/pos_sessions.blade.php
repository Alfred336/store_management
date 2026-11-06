@include('reports.pdf.partials.header')

<table class="data">
    <thead>
        <tr>
            <th>Opened</th>
            <th>Location</th>
            <th>Cashier</th>
            <th class="text-right">Opening Cash</th>
            <th class="text-right">Expected Cash</th>
            <th class="text-right">Counted Cash</th>
            <th class="text-right">Difference</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($sessions as $session)
            <tr>
                <td>{{ \Carbon\Carbon::parse($session->opened_at)->setTimezone('Africa/Dar_es_Salaam')->format('d M Y H:i') }}</td>
                <td>{{ $session->location_name }}</td>
                <td>{{ $session->cashier_name }}</td>
                <td class="text-right">{{ number_format($session->opening_cash, 2) }}</td>
                <td class="text-right">{{ $session->expected_cash !== null ? number_format($session->expected_cash, 2) : '—' }}</td>
                <td class="text-right">{{ $session->closing_cash_counted !== null ? number_format($session->closing_cash_counted, 2) : '—' }}</td>
                <td class="text-right">{{ $session->difference !== null ? number_format($session->difference, 2) : '—' }}</td>
                <td>{{ ucfirst($session->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:#999;">No sessions.</td></tr>
        @endforelse
    </tbody>
</table>

@include('reports.pdf.partials.footer')
