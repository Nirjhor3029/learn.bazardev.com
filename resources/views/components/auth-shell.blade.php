{{-- Centered card used by login / forgot password / reset password. --}}
@props(['title', 'subtitle' => null])

<x-layout :title="$title">
    <section class="mx-auto max-w-md px-4 pt-12 pb-20">
        <div class="text-center">
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-2 text-sm text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="mt-8 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
            {{ $slot }}
        </div>

        @isset($footer)
            <p class="mt-6 text-center text-sm text-gray-500">{{ $footer }}</p>
        @endisset
    </section>
</x-layout>
