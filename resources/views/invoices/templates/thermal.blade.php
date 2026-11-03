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
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 36pt; color: rgba(0,0,0,0.12); font-weight: bold;
    white-space: nowrap; z-index: 9999;
}
.fiscal { margin-top: 4pt; font-size: 7pt; border-top: 1px dashed #000; padding-top: 4pt; }
</style>
</head>
<body>
@if($watermark)<div class="watermark">{{ $watermark }}</div>@endif
<div class="page">
    <div class="center bold" style="font-size:10pt">{{ $company['name'] }}</div>
    @if($company['address'])<div class="center small">{{ $company['address'] }}</div>@endif
    @if($company['phone'])<div class="center small">{{ $company['phone'] }}</div>@endif
    @if($company['tin'])<div class="center small">TIN: {{ $company['tin'] }}@if($company['vrn']) | VRN: {{ $company['vrn'] }}@endif</div>@endif

    <hr class="sep">

    <div class="center bold">INVOICE</div>
    <div class="center">{{ $inv['number'] }}</div>
    @if($inv['issue_date'])<div class="center small">{{ $inv['issue_date'] }}</div>@endif

    <hr class="sep">

    <div class="small"><strong>To:</strong> {{ $customer['name'] }}</div>
    @if($customer['tin'])<div class="small">TIN: {{ $customer['tin'] }}</div>@endif
    @if($inv['location'])<div class="small">{{ $inv['location'] }}</div>@endif

    <hr class="sep">

    @foreach($lines as $line)
    <div class="bold">{{ $line['description'] }}</div>
    <table>
        <tr>
            <td>{{ $line['quantity'] }} x {{ $line['unit_price'] }}</td>
            <td class="text-right">{{ $line['line_total'] }}</td>
        </tr>
    </table>
    @endforeach

    <hr class="sep">

    <table>
        <tr><td class="small">Subtotal</td><td class="text-right small">{{ $totals['subtotal'] }}</td></tr>
        @if($totals['discount_total'] !== '0.00')
        <tr><td class="small">Discount</td><td class="text-right small">-{{ $totals['discount_total'] }}</td></tr>
        @endif
        <tr><td class="small">VAT</td><td class="text-right small">{{ $totals['tax_total'] }}</td></tr>
        <tr class="total-line"><td>TOTAL</td><td class="text-right">{{ $totals['total'] }}</td></tr>
        @if($totals['amount_paid'] !== '0.00')
        <tr><td class="small">Paid</td><td class="text-right small">{{ $totals['amount_paid'] }}</td></tr>
        <tr class="total-line"><td>BALANCE</td><td class="text-right">{{ $totals['balance'] }}</td></tr>
        @endif
    </table>

    @if($inv['void_reason'])
    <hr class="sep">
    <div class="small center" style="color:#000">VOID: {{ $inv['void_reason'] }}</div>
    @endif

    @if($fiscal)
    <div class="fiscal center">
        F: {{ $fiscal['number'] }}<br>{{ $fiscal['verification_code'] }}
    </div>
    @endif

    <hr class="sep">
    <div class="center small">{{ $totals['amount_in_words'] }}</div>
    @if($company['footer_terms'])<div class="center small">{{ $company['footer_terms'] }}</div>@endif
    @if($terms)<div class="center small">{{ $terms }}</div>@endif
</div>
</body>
</html>
