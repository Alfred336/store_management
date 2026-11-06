<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1a1a1a; margin: 0; padding: 0; }
.page { padding: 24pt 30pt; }
h1 { font-size: 16pt; margin: 0 0 2pt; }
h2 { font-size: 11pt; margin: 0; color: #444; }
.header-block { width: 100%; margin-bottom: 14pt; }
.header-block td { vertical-align: top; padding: 0; }
.company-name { font-size: 13pt; font-weight: bold; }
.filters { font-size: 8pt; color: #555; }
table.data { width: 100%; border-collapse: collapse; margin-top: 10pt; }
table.data th { background: #1a1a1a; color: #fff; text-align: left; padding: 5pt 6pt; font-size: 8.5pt; }
table.data th.text-right { text-align: right; }
table.data td { padding: 4pt 6pt; border-bottom: 1px solid #e8e8e8; font-size: 8.5pt; }
table.data td.text-right { text-align: right; font-family: monospace; }
table.data tr.total td { font-weight: bold; border-top: 2px solid #1a1a1a; border-bottom: none; background: #f5f5f5; }
.text-muted { color: #666; font-size: 7.5pt; }
.footer { margin-top: 16pt; border-top: 1px solid #ccc; padding-top: 6pt; font-size: 7.5pt; color: #888; }
</style>
</head>
<body>
<div class="page">
<table class="header-block"><tr>
    <td style="width:60%">
        <div class="company-name">{{ $company['name'] }}</div>
        @if($company['phone'])<div class="text-muted">{{ $company['phone'] }}</div>@endif
        @if($company['address'])<div class="text-muted">{{ $company['address'] }}</div>@endif
        @if($company['tin'])<div class="text-muted">TIN: {{ $company['tin'] }}</div>@endif
        @if($company['vrn'])<div class="text-muted">VRN: {{ $company['vrn'] }}</div>@endif
    </td>
    <td style="text-align:right">
        <h1>{{ $title }}</h1>
        <div class="filters">
        @if(isset($filters['as_of']))
            As of: {{ $filters['as_of'] }}
        @else
            From: {{ $filters['date_from'] }} &nbsp; To: {{ $filters['date_to'] }}
        @endif
        </div>
        <div class="text-muted">Generated: {{ now('Africa/Dar_es_Salaam')->format('d M Y H:i') }}</div>
    </td>
</tr></table>
