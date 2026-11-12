<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a1a; margin: 0; padding: 0; }
.page { padding: 30pt 36pt; }
h1 { font-size: 18pt; margin: 0 0 2pt; }
h2 { font-size: 11pt; margin: 0 0 4pt; }
table { width: 100%; border-collapse: collapse; }
th { background: #1a1a1a; color: #fff; text-align: left; padding: 5pt 6pt; font-size: 9pt; }
td { padding: 4pt 6pt; border-bottom: 1px solid #e0e0e0; font-size: 9pt; }
.text-right { text-align: right; }
.total-row td { font-weight: bold; border-top: 2px solid #1a1a1a; border-bottom: none; }
.section { margin-top: 18pt; }
.two-col { width: 100%; }
.two-col td { vertical-align: top; padding: 0; border: none; }
.label { color: #666; font-size: 8.5pt; }
.amount-words { font-style: italic; color: #444; margin-top: 6pt; font-size: 9pt; }
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 60pt; color: rgba(200,0,0,0.12); font-weight: bold;
    white-space: nowrap; letter-spacing: 8pt; z-index: 9999;
}
.not-tax-invoice { font-style: italic; color: #666; font-size: 8.5pt; margin-top: 6pt; }
.footer { margin-top: 20pt; border-top: 1px solid #ccc; padding-top: 8pt; font-size: 8pt; color: #666; }
</style>
</head>
<body>
@if($watermark)
    <div class="watermark">{{ $watermark }}</div>
@endif
<div class="page">
    <table class="two-col">
        <tr>
            <td style="width:60%">
                @if($company['logo'])
                    <img src="{{ $company['logo'] }}" style="max-height:60pt; max-width:160pt; margin-bottom:6pt" alt="logo">
                @endif
                <h1>{{ $company['name'] }}</h1>
                @if($company['address'])<div>{{ $company['address'] }}</div>@endif
                @if($company['phone'])<div>{{ $company['phone'] }}</div>@endif
                @if($company['email'])<div>{{ $company['email'] }}</div>@endif
                @if($company['tin'])<div class="label">TIN: {{ $company['tin'] }}</div>@endif
                @if($company['vrn'])<div class="label">VRN: {{ $company['vrn'] }}</div>@endif
            </td>
            <td style="width:40%; text-align:right">
                <h1 style="font-size:14pt; color:#333">QUOTATION</h1>
                <div><strong>{{ $quot['number'] }}</strong></div>
                @if($quot['revision'] > 1)<div class="label">Revision {{ $quot['revision'] }}</div>@endif
                <div class="label">Date: {{ $quot['quote_date'] }}</div>
                <div class="label">Valid until: {{ $quot['valid_until'] }}</div>
                @if($quot['location'])<div class="label">Location: {{ $quot['location'] }}</div>@endif
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>Quotation For</h2>
        <div><strong>{{ $customer['name'] }}</strong></div>
        @if($customer['address'])<div>{{ $customer['address'] }}</div>@endif
        @if($customer['phone'])<div>{{ $customer['phone'] }}</div>@endif
        @if($customer['email'])<div>{{ $customer['email'] }}</div>@endif
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>SKU</th>
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
                    <td>{{ $line['description'] }}</td>
                    <td>{{ $line['sku'] }}</td>
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

    <div class="section">
        <table style="width:220pt; margin-left:auto">
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
    <div class="section"><h2>Notes</h2><div>{{ $notes }}</div></div>
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
