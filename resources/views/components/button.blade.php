@props(['variant' => 'primary', 'type' => 'submit'])

@php
    $styles = [
        'primary' => 'bg-blue-600 text-white shadow-sm hover:bg-blue-700 focus-visible:ring-blue-200',
        'secondary' => 'border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:ring-gray-100',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus-visible:ring-rose-200',
    ][$variant];
@endphp

<button type="{{ $type }}"
        {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-4 {$styles}"]) }}>
    {{ $slot }}
</button>
