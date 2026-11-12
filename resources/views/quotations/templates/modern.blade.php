<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; margin: 0; padding: 0; }
.page { padding: 0; }
.header-band { background: #0066cc; color: #fff; padding: 20pt 30pt; }
.header-band h1 { font-size: 20pt; margin: 0; }
.header-band .meta { font-size: 9pt; opacity: 0.85; margin-top: 4pt; }
.body { padding: 20pt 30pt; }
table { width: 100%; border-collapse: collapse; }
thead th { background: #0066cc; color: #fff; padding: 5pt 7pt; font-size: 9pt; text-align: left; }
tbody td { padding: 5pt 7pt; border-bottom: 1px solid #e8e8e8; font-size: 9pt; }
tbody tr:nth-child(even) td { background: #f5f9ff; }
.text-right { text-align: right; }
.two-col td { vertical-align: top; padding: 0 10pt 0 0; border: none; }
.label { color: #888; font-size: 8.5pt; }
.section { margin-top: 16pt; }
.totals-table { width: 200pt; margin-left: auto; border-collapse: collapse; }
.totals-table td { padding: 3pt 6pt; font-size: 9.5pt; }
.total-line td { font-weight: bold; border-top: 2px solid #0066cc; }
.amount-words { font-style: italic; color: #555; margin-top: 6pt; font-size: 9pt; }
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 60pt; color: rgba(0,100,200,0.10); font-weight: bold;
    white-space: nowrap; letter-spacing: 8pt; z-index: 9999;
}
.not-tax-invoice { font-style: italic; color: #888; font-size: 8.5pt; margin-top: 6pt; }
.footer { margin-top: 20pt; border-top: 1px solid #ddd; padding-top: 8pt; font-size: 8pt; color: #888; }
</style>
</head>
<body>
@if($watermark)
    <div class="watermark">{{ $watermark }}</div>
@endif
<div class="page">
    <div class="header-band">
        <table class="two-col">
            <tr>
                <td style="width:60%">
                    @if($company['logo'])
                        <img src="{{ $company['logo'] }}" style="max-height:50pt; max-width:140pt; margin-bottom:6pt" alt="logo">
                    @endif
                    <h1>{{ $company['name'] }}</h1>
                    <div class="meta">{{ $company['address'] }}</div>
                    @if($company['phone'])<div class="meta">{{ $company['phone'] }}</div>@endif
                    @if($company['tin'])<div class="meta">TIN: {{ $company['tin'] }}</div>@endif
                </td>
                <td style="width:40%; text-align:right">
                    <div style="font-size:18pt; font-weight:bold">QUOTATION</div>
                    <div>{{ $quot['number'] }}</div>
                    @if($quot['revision'] > 1)<div class="meta">Revision {{ $quot['revision'] }}</div>@endif
                    <div class="meta">Date: {{ $quot['quote_date'] }}</div>
                    <div class="meta">Valid until: {{ $quot['valid_until'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">
        <div class="section">
            <table class="two-col">
                <tr>
                    <td style="width:50%">
                        <div class="label">Quotation for</div>
                        <div><strong>{{ $customer['name'] }}</strong></div>
                        @if($customer['address'])<div>{{ $customer['address'] }}</div>@endif
                        @if($customer['phone'])<div>{{ $customer['phone'] }}</div>@endif
                        @if($customer['email'])<div>{{ $customer['email'] }}</div>@endif
                    </td>
                    <td style="width:50%; text-align:right">
                        <div class="label">Location</div>
                        <div>{{ $quot['location'] }}</div>
                    </td>
                </tr>
            </table>
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
            <table class="totals-table">
                <tr><td class="label">Subtotal</td><td class="text-right">{{ $totals['subtotal'] }}</td></tr>
                @if($totals['discount_total'] !== '0.00')
                <tr><td class="label">Discount</td><td class="text-right">-{{ $totals['discount_total'] }}</td></tr>
                @endif
                <tr><td class="label">VAT</td><td class="text-right">{{ $totals['tax_total'] }}</td></tr>
                <tr class="total-line"><td>Total</td><td class="text-right">{{ $totals['total'] }}</td></tr>
            </table>
            <div class="amount-words">{{ $totals['amount_in_words'] }}</div>
            <div class="not-tax-invoice">This is not a tax invoice.</div>
        </div>

        @if($notes)
        <div class="section"><strong>Notes</strong><div>{{ $notes }}</div></div>
        @endif

        @if($company['bank_details'] || $terms || $company['footer_terms'])
        <div class="footer">
            @if($company['bank_details'])<div>{{ $company['bank_details'] }}</div>@endif
            @if($terms)<div>{{ $terms }}</div>@elseif($company['footer_terms'])<div>{{ $company['footer_terms'] }}</div>@endif
        </div>
        @endif
    </div>
</div>
</body>
</html>
