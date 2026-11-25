@props(['status'])

@php
    $styles = [
        'paid' => 'bg-emerald-50 text-emerald-700',
        'pending' => 'bg-blue-50 text-blue-700',
        'manual_review' => 'bg-amber-50 text-amber-800',
        'failed' => 'bg-rose-50 text-rose-700',
        'expired' => 'bg-gray-100 text-gray-500',
        'refunded' => 'bg-violet-50 text-violet-700',
    ][$status] ?? 'bg-gray-100 text-gray-500';

    $label = str_replace('_', ' ', $status);
@endphp

<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize {{ $styles }}">{{ $label }}</span>
