@php
    $mark = [
        'today' => ['label' => 'Today', 'bar' => 'bg-blue-600', 'text' => 'text-blue-700'],
        'completed' => ['label' => 'Done', 'bar' => 'bg-emerald-600', 'text' => 'text-emerald-700'],
        'missed' => ['label' => 'Not done yet', 'bar' => 'bg-amber-500', 'text' => 'text-amber-700'],
        'upcoming' => ['label' => 'Not started', 'bar' => 'bg-stone-300', 'text' => 'text-stone-400'],
    ][$selected['status']];
@endphp

<div data-day-detail class="flex gap-4">
    <div class="w-1 shrink-0 rounded-full {{ $mark['bar'] }}"></div>

    <div class="min-w-0 flex-1 py-1">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-lg font-semibold text-stone-900">Day {{ $selectedDay }}</h2>
            <span class="text-sm font-medium {{ $mark['text'] }}">{{ $mark['label'] }}</span>
        </div>

        @if (isset($selected['mock_test']))
            <p class="mt-2 text-sm text-stone-600">{{ $selected['note'] }}</p>
            <a href="{{ route('mock-tests.index') }}" class="mt-4 inline-block rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Open Mock Tests</a>
        @elseif (empty($selected['chapters']))
            <p class="mt-2 text-sm text-stone-600">{{ $selected['note'] ?? 'Nothing scheduled for this day.' }}</p>
        @else
            <p class="mt-1 text-sm text-stone-500">{{ collect($selected['chapters'])->pluck('course_title')->unique()->implode(' and ') }} &mdash; about {{ $selected['minutes'] }} minutes</p>
            <div class="mt-3">
                @foreach ($selected['chapters'] as $chapter)
                    @include('study-plan.partials.chapter-row', ['chapter' => $chapter])
                @endforeach
            </div>
        @endif
    </div>
</div>
