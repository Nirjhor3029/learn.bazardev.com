{{-- Thin progress bar. Blue while in progress, green once everything is done. --}}
@props(['done' => 0, 'total' => 0, 'live' => false])

@php
    $percent = $total > 0 ? (int) round($done / $total * 100) : 0;
    $complete = $total > 0 && $done >= $total;
@endphp

<div {{ $attributes->merge(['class' => 'h-1.5 overflow-hidden rounded-full bg-gray-100']) }}
     role="progressbar" aria-label="Chapters done" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}"
     @if ($live) data-progress @endif>
    <div class="h-full rounded-full transition-all duration-300 {{ $complete ? 'bg-emerald-500' : 'bg-blue-600' }}"
         style="width: {{ $percent }}%" @if ($live) data-progress-fill @endif></div>
</div>
