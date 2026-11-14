@props(['amount', 'currency' => 'TZS', 'decimals' => 0])

@php
// Format as "TZS 1,250,000" (no decimal places by default for TZS)
$formatted = number_format((float) $amount, $decimals, '.', ',');
@endphp

<span {{ $attributes }}>{{ $currency }} {{ $formatted }}</span>
