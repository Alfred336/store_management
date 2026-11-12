<x-mail::message>
# Quotation {{ $quotation->number ?? 'Draft' }}

Dear {{ $quotation->customer?->name ?? $quotation->prospect_name ?? 'Customer' }},

@if($message)
{{ $message }}
@else
Please find attached the quotation from {{ config('app.name') }}.
@endif

**Quotation details:**
- Number: {{ $quotation->number ?? 'Draft' }}
- Date: {{ $quotation->quote_date->format('d M Y') }}
- Valid until: {{ $quotation->valid_until->format('d M Y') }}
- Total: {{ $quotation->currency }} {{ number_format((float) $quotation->total, 2) }}

The quotation is attached as a PDF. Please note: **this is not a tax invoice.**

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
