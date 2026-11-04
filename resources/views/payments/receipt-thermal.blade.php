<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #000; margin: 0; padding: 0; }
.page { padding: 6pt 8pt; }
.center { text-align: center; }
.bold { font-weight: bold; }
.small { font-size: 7pt; }
.sep { border: none; border-top: 1px dashed #000; margin: 4pt 0; }
table { width: 100%; border-collapse: collapse; }
td { padding: 1pt 2pt; font-size: 7.5pt; vertical-align: top; }
.text-right { text-align: right; }
.total-line { font-weight: bold; border-top: 1px solid #000; }
@if($payment['status'] === 'reversed')
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 36pt; color: rgba(0,0,0,0.12); font-weight: bold;
    white-space: nowrap; z-index: 9999;
}
@endif
</style>
</head>
<body>
@if($payment['status'] === 'reversed')
<div class="watermark">REVERSED</div>
@endif
<div class="page">
    <div class="center bold" style="font-size:10pt">{{ $company['name'] }}</div>
    @if($company['address'])<div class="center small">{{ $company['address'] }}</div>@endif
    @if($company['phone'])<div class="center small">{{ $company['phone'] }}</div>@endif
    @if($company['tin'])<div class="center small">TIN: {{ $company['tin'] }}@if($company['vrn']) | VRN: {{ $company['vrn'] }}@endif</div>@endif

    <hr class="sep">

    <div class="center bold">PAYMENT RECEIPT</div>
    <div class="center" style="font-family:monospace">{{ $payment['number'] }}</div>
    @if($payment['received_at'])<div class="center small">{{ $payment['received_at'] }}</div>@endif

    <hr class="sep">

    <div class="small"><strong>From:</strong> {{ $customer['name'] }}</div>

    <hr class="sep">

    <table>
        <tr><td class="small">Method</td><td class="text-right small">{{ $payment['method'] }}</td></tr>
        @if($payment['provider'])
        <tr><td class="small">Provider</td><td class="text-right small">{{ $payment['provider'] }}</td></tr>
        @endif
        @if($payment['reference'])
        <tr><td class="small">Reference</td><td class="text-right small" style="font-family:monospace">{{ $payment['reference'] }}</td></tr>
        @endif
        <tr><td class="small">Received by</td><td class="text-right small">{{ $receiver }}</td></tr>
        <tr class="total-line"><td>AMOUNT</td><td class="text-right" style="font-family:monospace">{{ $company['currency'] }} {{ $payment['amount'] }}</td></tr>
    </table>

    @if(count($allocations) > 0)
    <hr class="sep">
    <div class="small bold">Applied to invoices:</div>
    @foreach($allocations as $alloc)
    <table>
        <tr>
            <td class="small" style="font-family:monospace">{{ $alloc['invoice_number'] }}</td>
            <td class="text-right small" style="font-family:monospace">{{ $alloc['amount'] }}</td>
        </tr>
    </table>
    @endforeach
    @endif

    @if($payment['notes'])
    <hr class="sep">
    <div class="small">{{ $payment['notes'] }}</div>
    @endif

    <hr class="sep">
    @if($company['footer_terms'])<div class="center small">{{ $company['footer_terms'] }}</div>@endif
    <div class="center small">Thank you for your payment.</div>
</div>
</body>
</html>
