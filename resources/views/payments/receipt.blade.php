<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a1a; margin: 0; padding: 0; }
.page { padding: 30pt 36pt; }
h1 { font-size: 18pt; margin: 0 0 2pt; }
table { width: 100%; border-collapse: collapse; }
th { background: #1a1a1a; color: #fff; text-align: left; padding: 5pt 6pt; font-size: 9pt; }
td { padding: 4pt 6pt; border-bottom: 1px solid #e0e0e0; font-size: 9pt; }
.text-right { text-align: right; }
.label { color: #666; font-size: 8.5pt; }
.section { margin-top: 18pt; }
.two-col { width: 100%; }
.two-col td { vertical-align: top; padding: 0; border: none; }
.total-row td { font-weight: bold; border-top: 2px solid #1a1a1a; border-bottom: none; font-size: 11pt; }
.footer { margin-top: 20pt; border-top: 1px solid #ccc; padding-top: 8pt; font-size: 8pt; color: #666; }
@if($payment['status'] === 'reversed')
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 60pt; color: rgba(200,0,0,0.12); font-weight: bold;
    white-space: nowrap; letter-spacing: 8pt; z-index: 9999;
}
@endif
</style>
</head>
<body>
@if($payment['status'] === 'reversed')
<div class="watermark">REVERSED</div>
@endif
<div class="page">
    <table class="two-col"><tr>
        <td style="width:60%">
            <h1>{{ $company['name'] }}</h1>
            @if($company['address'])<div class="label">{{ $company['address'] }}</div>@endif
            @if($company['phone'])<div class="label">{{ $company['phone'] }}</div>@endif
            @if($company['tin'])<div class="label">TIN: {{ $company['tin'] }}@if($company['vrn']) &nbsp;|&nbsp; VRN: {{ $company['vrn'] }}@endif</div>@endif
        </td>
        <td style="text-align:right">
            <div style="font-size:14pt; font-weight:bold">PAYMENT RECEIPT</div>
            <div style="font-size:11pt; font-family:monospace">{{ $payment['number'] }}</div>
            @if($payment['received_at'])<div class="label">{{ $payment['received_at'] }}</div>@endif
        </td>
    </tr></table>

    <div class="section">
        <table class="two-col"><tr>
            <td style="width:50%">
                <div class="label">Received from</div>
                <div style="font-weight:bold">{{ $customer['name'] }}</div>
            </td>
            <td style="width:50%">
                <div class="label">Received by</div>
                <div>{{ $receiver }}</div>
            </td>
        </tr></table>
    </div>

    <div class="section">
        <table>
            <tr>
                <th>Payment method</th>
                <th>Provider</th>
                <th>Reference</th>
                <th class="text-right">Amount ({{ $company['currency'] }})</th>
            </tr>
            <tr>
                <td>{{ $payment['method'] }}</td>
                <td>{{ $payment['provider'] ?? '—' }}</td>
                <td style="font-family:monospace">{{ $payment['reference'] ?? '—' }}</td>
                <td class="text-right" style="font-family:monospace">{{ $payment['amount'] }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="3">Total received</td>
                <td class="text-right" style="font-family:monospace">{{ $payment['amount'] }}</td>
            </tr>
        </table>
    </div>

    @if(count($allocations) > 0)
    <div class="section">
        <div style="font-weight:bold; margin-bottom:4pt">Applied to invoices</div>
        <table>
            <tr>
                <th>Invoice</th>
                <th class="text-right">Amount applied</th>
            </tr>
            @foreach($allocations as $alloc)
            <tr>
                <td style="font-family:monospace">{{ $alloc['invoice_number'] }}</td>
                <td class="text-right" style="font-family:monospace">{{ $alloc['amount'] }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    @if($payment['notes'])
    <div class="section">
        <div class="label">Notes</div>
        <div>{{ $payment['notes'] }}</div>
    </div>
    @endif

    <div class="footer">
        @if($company['footer_terms'])<div>{{ $company['footer_terms'] }}</div>@endif
        <div>This is a computer-generated receipt and does not require a signature.</div>
    </div>
</div>
</body>
</html>
