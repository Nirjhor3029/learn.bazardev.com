@php
    $available = $course['status'] === 'available';
    $isPaid = ($coursePricing ?? null) && ! $coursePricing->is_free && $coursePricing->price_amount > 0;
    $locked = $isPaid && ! ($owned ?? false);
@endphp
<a href="{{ $available ? route('course.show', $slug) : '#' }}"
   class="group flex flex-col rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition
          {{ $available ? 'hover:border-blue-200 hover:shadow-md' : 'opacity-60 cursor-not-allowed' }}">
    <div class="flex items-center justify-between gap-3">
        <h2 class="flex items-center gap-2 text-lg font-semibold"><span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 tabular-nums">{{ $number }}</span>{{ $course['title'] }}</h2>
        <span class="flex shrink-0 items-center gap-1.5">
            @if ($locked)
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800"><x-icon name="lock" class="h-3 w-3" /> {{ number_format($coursePricing->price_amount) }} {{ $coursePricing->currency }}</span>
            @endif
            @if ($mine && $mine['done'] === $mine['total'])
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700"><x-icon name="check" class="h-3 w-3" /> Completed</span>
            @elseif ($available)
                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Ready</span>
            @else
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Soon</span>
            @endif
        </span>
    </div>
    <p class="mt-2 text-sm text-gray-500">{{ $course['tagline'] }}</p>

    @if ($mine)
        <div class="mt-auto flex items-center gap-3 pt-5">
            <x-progress :done="$mine['done']" :total="$mine['total']" class="flex-1" />
            <span class="text-xs font-medium text-gray-500 tabular-nums">{{ $mine['done'] }}/{{ $mine['total'] }} done</span>
        </div>
    @endif
</a>
