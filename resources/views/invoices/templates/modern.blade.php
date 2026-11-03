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
.balance-line td { font-weight: bold; font-size: 11pt; color: #0066cc; }
.amount-words { font-style: italic; color: #555; margin-top: 6pt; font-size: 9pt; }
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(-45deg);
    font-size: 60pt; color: rgba(0,100,200,0.10); font-weight: bold;
    white-space: nowrap; letter-spacing: 8pt; z-index: 9999;
}
.fiscal { margin-top: 12pt; padding: 8pt; background: #f0f8ff; border-left: 3px solid #0066cc; }
.footer { margin-top: 20pt; border-top: 1px solid #ccc; padding-top: 8pt; font-size: 8pt; color: #666; }
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
                        <img src="{{ $company['logo'] }}" style="max-height:50pt; max-width:140pt; margin-bottom:4pt" alt="logo">
                    @else
                        <h1>{{ $company['name'] }}</h1>
                    @endif
                    <div class="meta">
                        @if($company['address']){{ $company['address'] }} &nbsp;@endif
                        @if($company['phone']){{ $company['phone'] }}@endif
                    </div>
                    @if($company['tin'])<div class="meta">TIN: {{ $company['tin'] }}@if($company['vrn']) &nbsp; VRN: {{ $company['vrn'] }}@endif</div>@endif
                </td>
                <td style="width:40%; text-align:right">
                    <div style="font-size:14pt; font-weight:bold; letter-spacing:2pt">INVOICE</div>
                    <div style="font-size:13pt; margin-top:4pt">{{ $inv['number'] }}</div>
                    @if($inv['issue_date'])<div class="meta">{{ $inv['issue_date'] }}</div>@endif
                    @if($inv['due_date'])<div class="meta">Due: {{ $inv['due_date'] }}</div>@endif
                </td>
            </tr>
        </table>
    </div>

    <div class="body">
        <div class="section">
            <div class="label">Bill To</div>
            <div><strong>{{ $customer['name'] }}</strong></div>
            @if($customer['address'])<div>{{ $customer['address'] }}</div>@endif
            @if($customer['phone'])<div>{{ $customer['phone'] }}</div>@endif
            @if($customer['tin'])<div class="label">TIN: {{ $customer['tin'] }}@if($customer['vrn']) &nbsp; VRN: {{ $customer['vrn'] }}@endif</div>@endif
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
                        <td>{{ $line['description'] }}@if($line['sku']) <span class="label">{{ $line['sku'] }}</span>@endif</td>
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
                @if($totals['amount_paid'] !== '0.00')
                <tr><td class="label">Paid</td><td class="text-right">{{ $totals['amount_paid'] }}</td></tr>
                <tr class="balance-line"><td>Balance</td><td class="text-right">{{ $totals['balance'] }}</td></tr>
                @endif
            </table>
            <div class="amount-words">{{ $totals['amount_in_words'] }}</div>
        </div>

        @if($notes)<div class="section"><strong>Notes</strong><div>{{ $notes }}</div></div>@endif
        @if($inv['void_reason'])<div class="section" style="color:#c00"><strong>Void reason:</strong> {{ $inv['void_reason'] }}</div>@endif

        @if($fiscal)
        <div class="fiscal section">
            <strong>Fiscal:</strong> No: {{ $fiscal['number'] }} &nbsp; Code: {{ $fiscal['verification_code'] }}
        </div>
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
