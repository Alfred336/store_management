<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #333; margin: 0; }
.page { padding: 36pt 40pt; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; border-bottom: 2px solid #333; padding: 4pt 5pt; font-size: 9pt; }
td { padding: 4pt 5pt; border-bottom: 1px solid #ddd; font-size: 9pt; }
.text-right { text-align: right; }
.two-col td { vertical-align: top; padding: 0; border: none; }
.label { color: #888; font-size: 8.5pt; }
.section { margin-top: 18pt; }
.amount-words { font-style: italic; color: #666; font-size: 9pt; margin-top: 5pt; }
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 60pt; color: rgba(0,0,0,0.07); font-weight: bold;
    white-space: nowrap; z-index: 9999;
}
.fiscal { margin-top: 12pt; font-size: 9pt; border-top: 1px solid #ccc; padding-top: 8pt; }
.footer { margin-top: 24pt; font-size: 8pt; color: #999; border-top: 1px solid #eee; padding-top: 8pt; }
</style>
</head>
<body>
@if($watermark)<div class="watermark">{{ $watermark }}</div>@endif
<div class="page">
    <table class="two-col">
        <tr>
            <td style="width:55%">
                @if($company['logo'])<img src="{{ $company['logo'] }}" style="max-height:50pt; max-width:130pt; margin-bottom:6pt" alt="logo"><br>@endif
                <strong>{{ $company['name'] }}</strong><br>
                @if($company['address'])<span class="label">{{ $company['address'] }}</span><br>@endif
                @if($company['tin'])<span class="label">TIN {{ $company['tin'] }}</span>@if($company['vrn']) &nbsp; <span class="label">VRN {{ $company['vrn'] }}</span>@endif@endif
            </td>
            <td style="width:45%; text-align:right">
                <div style="font-size:16pt; font-weight:bold; color:#222">INVOICE</div>
                <div style="font-size:11pt">{{ $inv['number'] }}</div>
                @if($inv['issue_date'])<div class="label">{{ $inv['issue_date'] }}</div>@endif
                @if($inv['due_date'])<div class="label">Due {{ $inv['due_date'] }}</div>@endif
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="label">To</div>
        <strong>{{ $customer['name'] }}</strong>
        @if($customer['address'])<br><span>{{ $customer['address'] }}</span>@endif
        @if($customer['tin'])<br><span class="label">TIN {{ $customer['tin'] }}</span>@endif
    </div>

    <div class="section">
        <table>
            <thead><tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
            </tr></thead>
            <tbody>
            @foreach($lines as $line)
            <tr>
                <td>{{ $line['description'] }}@if($line['sku']) <span class="label">[{{ $line['sku'] }}]</span>@endif</td>
                <td class="text-right">{{ $line['quantity'] }}</td>
                <td class="text-right">{{ $line['unit_price'] }}</td>
                <td class="text-right">{{ $line['line_tax'] }}</td>
                <td class="text-right">{{ $line['line_total'] }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <table style="width:180pt; margin-left:auto">
            <tr><td class="label">Subtotal</td><td class="text-right">{{ $totals['subtotal'] }}</td></tr>
            @if($totals['discount_total'] !== '0.00')
            <tr><td class="label">Discount</td><td class="text-right">-{{ $totals['discount_total'] }}</td></tr>
            @endif
            <tr><td class="label">VAT</td><td class="text-right">{{ $totals['tax_total'] }}</td></tr>
            <tr><td style="font-weight:bold; border-top:2px solid #333">Total</td><td class="text-right" style="font-weight:bold; border-top:2px solid #333">{{ $totals['total'] }}</td></tr>
            @if($totals['amount_paid'] !== '0.00')
            <tr><td class="label">Paid</td><td class="text-right">{{ $totals['amount_paid'] }}</td></tr>
            <tr><td style="font-weight:bold">Balance</td><td class="text-right" style="font-weight:bold">{{ $totals['balance'] }}</td></tr>
            @endif
        </table>
        <div class="amount-words">{{ $totals['amount_in_words'] }}</div>
    </div>

    @if($notes)<div class="section"><strong>Notes</strong><br>{{ $notes }}</div>@endif
    @if($inv['void_reason'])<div class="section" style="color:#c00">Void: {{ $inv['void_reason'] }}</div>@endif

    @if($fiscal)
    <div class="fiscal">Fiscal No: {{ $fiscal['number'] }} | Code: {{ $fiscal['verification_code'] }}</div>
    @endif

    @if($company['bank_details'] || $terms || $company['footer_terms'])
    <div class="footer">
        @if($company['bank_details']){{ $company['bank_details'] }}<br>@endif
        @if($terms){{ $terms }}@elseif($company['footer_terms']){{ $company['footer_terms'] }}@endif
    </div>
    @endif
</div>
</body>
</html>
