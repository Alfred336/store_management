@props(['value', 'format' => 'j M Y'])
{{-- Renders e.g. "13 Nov 2026" --}}
@php
$date = $value instanceof \DateTimeInterface
    ? \Carbon\Carbon::instance($value)
    : \Carbon\Carbon::parse($value);
@endphp
<time {{ $attributes }} datetime="{{ $date->toIso8601String() }}">{{ $date->format($format) }}</time>
