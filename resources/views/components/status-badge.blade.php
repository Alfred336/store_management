@props(['status'])

@php
// Canonical colour map for all statuses across the app
$map = [
    // Invoice
    'draft'          => ['label' => 'Draft',          'class' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'],
    'issued'         => ['label' => 'Issued',         'class' => 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'],
    'partially_paid' => ['label' => 'Partial',        'class' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'],
    'paid'           => ['label' => 'Paid',           'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
    'void'           => ['label' => 'Void',           'class' => 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400'],
    'overdue'        => ['label' => 'Overdue',        'class' => 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400'],
    // Quotation
    'sent'           => ['label' => 'Sent',           'class' => 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'],
    'accepted'       => ['label' => 'Accepted',       'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
    'rejected'       => ['label' => 'Rejected',       'class' => 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400'],
    'converted'      => ['label' => 'Converted',      'class' => 'bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-400'],
    'expired'        => ['label' => 'Expired',        'class' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'],
    // Payment
    'active'         => ['label' => 'Active',         'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
    'reversed'       => ['label' => 'Reversed',       'class' => 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400'],
    // Stock / generic
    'low_stock'      => ['label' => 'Low Stock',      'class' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'],
    'out_of_stock'   => ['label' => 'Out of Stock',   'class' => 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400'],
    'in_stock'       => ['label' => 'In Stock',       'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
    // Generic
    'inactive'       => ['label' => 'Inactive',       'class' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'],
];

$key    = is_string($status) ? strtolower($status) : (is_object($status) && method_exists($status, 'value') ? $status->value : (string) $status);
$entry  = $map[$key] ?? ['label' => ucfirst(str_replace('_', ' ', $key)), 'class' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'];
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {$entry['class']}") }}>
    {{ $entry['label'] }}
</span>
