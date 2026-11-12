<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #333; margin: 0; padding: 0; }
.page { padding: 28pt 32pt; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; padding: 4pt 5pt; font-size: 8.5pt; border-bottom: 1.5px solid #333; }
td { padding: 4pt 5pt; border-bottom: 1px solid #eee; font-size: 8.5pt; }
.text-right { text-align: right; }
.label { color: #888; font-size: 8pt; }
.total-row td { font-weight: bold; border-top: 1.5px solid #333; border-bottom: none; }
.section { margin-top: 16pt; }
.two-col td { vertical-align: top; padding: 0 10pt 0 0; border: none; }
.amount-words { font-style: italic; color: #666; margin-top: 5pt; font-size: 8.5pt; }
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 60pt; color: rgba(0,0,0,0.07); font-weight: bold;
    white-space: nowrap; letter-spacing: 8pt; z-index: 9999;
}
.not-tax-invoice { font-style: italic; color: #999; font-size: 8pt; margin-top: 5pt; }
.footer { margin-top: 18pt; border-top: 1px solid #ddd; padding-top: 7pt; font-size: 7.5pt; color: #999; }
</style>
</head>
<body>
@if($watermark)
    <div class="watermark">{{ $watermark }}</div>
@endif
<div class="page">
    <table class="two-col">
        <tr>
            <td style="width:65%">
                @if($company['logo'])
                    <img src="{{ $company['logo'] }}" style="max-height:50pt; max-width:120pt; margin-bottom:5pt" alt="logo">
                @endif
                <div style="font-size:12pt; font-weight:bold">{{ $company['name'] }}</div>
                @if($company['address'])<div class="label">{{ $company['address'] }}</div>@endif
                @if($company['phone'])<div class="label">{{ $company['phone'] }}</div>@endif
                @if($company['tin'])<div class="label">TIN: {{ $company['tin'] }}</div>@endif
            </td>
            <td style="width:35%; text-align:right">
                <div style="font-size:13pt; font-weight:bold">QUOTATION</div>
                <div>{{ $quot['number'] }}</div>
                @if($quot['revision'] > 1)<div class="label">Rev. {{ $quot['revision'] }}</div>@endif
                <div class="label">{{ $quot['quote_date'] }}</div>
                <div class="label">Valid: {{ $quot['valid_until'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div><strong>{{ $customer['name'] }}</strong></div>
        @if($customer['address'])<div class="label">{{ $customer['address'] }}</div>@endif
        @if($customer['phone'])<div class="label">{{ $customer['phone'] }}</div>@endif
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit price</th>
                    <th class="text-right">Discount</th>
                    <th class="text-right">Tax</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                <tr>
                    <td>{{ $line['description'] }}@if($line['sku']) <span class="label">({{ $line['sku'] }})</span>@endif</td>
                    <td class="text-right">{{ $line['quantity'] }} {{ $line['unit'] }}</td>
                    <td class="text-right">{{ $line['unit_price'] }}</td>
                    <td class="text-right">{{ $line['discount'] }}</td>
                    <td class="text-right">{{ $line['line_tax'] }}</td>
                    <td class="text-right">{{ $line['line_total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="text-align:right; margin-top:10pt">
        <table style="width:180pt; margin-left:auto">
            <tr><td class="label">Subtotal</td><td class="text-right">{{ $totals['subtotal'] }}</td></tr>
            @if($totals['discount_total'] !== '0.00')
            <tr><td class="label">Discount</td><td class="text-right">-{{ $totals['discount_total'] }}</td></tr>
            @endif
            <tr><td class="label">VAT</td><td class="text-right">{{ $totals['tax_total'] }}</td></tr>
            <tr class="total-row"><td>Total</td><td class="text-right">{{ $totals['total'] }}</td></tr>
        </table>
        <div class="amount-words">{{ $totals['amount_in_words'] }}</div>
        <div class="not-tax-invoice">This is not a tax invoice.</div>
    </div>

    @if($notes)
    <div class="section"><div class="label">Notes</div><div>{{ $notes }}</div></div>
    @endif

    @if($company['bank_details'] || $terms || $company['footer_terms'])
    <div class="footer">
        @if($company['bank_details'])<div>{{ $company['bank_details'] }}</div>@endif
        @if($terms)<div>{{ $terms }}</div>@elseif($company['footer_terms'])<div>{{ $company['footer_terms'] }}</div>@endif
    </div>
    @endif
</div>
</body>
</html>
