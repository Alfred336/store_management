<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #222; margin: 0; }
.page { padding: 18pt 22pt; }
table { width: 100%; border-collapse: collapse; }
th { background: #333; color: #fff; padding: 3pt 4pt; font-size: 8pt; text-align: left; }
td { padding: 3pt 4pt; border-bottom: 1px solid #e0e0e0; font-size: 8pt; }
.text-right { text-align: right; }
.two-col td { vertical-align: top; padding: 0; border: none; }
.label { color: #777; font-size: 7.5pt; }
.section { margin-top: 10pt; }
.amount-words { font-style: italic; color: #555; font-size: 8pt; margin-top: 4pt; }
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 48pt; color: rgba(0,0,0,0.08); font-weight: bold;
    white-space: nowrap; z-index: 9999;
}
.fiscal { margin-top: 10pt; font-size: 8pt; border-top: 1px solid #ccc; padding-top: 6pt; }
.footer { margin-top: 14pt; font-size: 7.5pt; color: #999; border-top: 1px solid #eee; padding-top: 6pt; }
</style>
</head>
<body>
@if($watermark)<div class="watermark">{{ $watermark }}</div>@endif
<div class="page">
    <table class="two-col">
        <tr>
            <td style="width:60%">
                @if($company['logo'])<img src="{{ $company['logo'] }}" style="max-height:36pt; max-width:100pt; margin-bottom:3pt" alt="logo"><br>@endif
                <strong>{{ $company['name'] }}</strong>
                @if($company['phone'])<br><span class="label">{{ $company['phone'] }}</span>@endif
                @if($company['tin'])<br><span class="label">TIN: {{ $company['tin'] }}</span>@endif
            </td>
            <td style="width:40%; text-align:right">
                <strong>INVOICE</strong><br>
                <span>{{ $inv['number'] }}</span><br>
                @if($inv['issue_date'])<span class="label">{{ $inv['issue_date'] }}</span><br>@endif
                @if($inv['due_date'])<span class="label">Due: {{ $inv['due_date'] }}</span>@endif
            </td>
        </tr>
    </table>

    <div class="section">
        <span class="label">To: </span><strong>{{ $customer['name'] }}</strong>
        @if($customer['tin']) <span class="label"> TIN: {{ $customer['tin'] }}</span>@endif
    </div>

    <div class="section">
        <table>
            <thead><tr>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
            </tr></thead>
            <tbody>
            @foreach($lines as $line)
            <tr>
                <td>{{ $line['description'] }}</td>
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
        <table style="width:160pt; margin-left:auto">
            <tr><td class="label">Subtotal</td><td class="text-right">{{ $totals['subtotal'] }}</td></tr>
            @if($totals['discount_total'] !== '0.00')
            <tr><td class="label">Discount</td><td class="text-right">-{{ $totals['discount_total'] }}</td></tr>
            @endif
            <tr><td class="label">VAT</td><td class="text-right">{{ $totals['tax_total'] }}</td></tr>
            <tr><td style="font-weight:bold; border-top:1px solid #333">Total</td><td class="text-right" style="font-weight:bold; border-top:1px solid #333">{{ $totals['total'] }}</td></tr>
            @if($totals['amount_paid'] !== '0.00')
            <tr><td class="label">Paid</td><td class="text-right">{{ $totals['amount_paid'] }}</td></tr>
            <tr><td style="font-weight:bold">Balance</td><td class="text-right" style="font-weight:bold">{{ $totals['balance'] }}</td></tr>
            @endif
        </table>
        <div class="amount-words">{{ $totals['amount_in_words'] }}</div>
    </div>

    @if($inv['void_reason'])<div class="section" style="color:#c00; font-size:8pt">Void: {{ $inv['void_reason'] }}</div>@endif

    @if($fiscal)
    <div class="fiscal">F: {{ $fiscal['number'] }} | {{ $fiscal['verification_code'] }}</div>
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
