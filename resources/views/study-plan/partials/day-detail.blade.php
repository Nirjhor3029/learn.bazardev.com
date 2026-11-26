@php
    $badge = [
        'today' => ['label' => 'In progress', 'class' => 'bg-blue-100 text-blue-700'],
        'completed' => ['label' => 'Completed', 'class' => 'bg-emerald-100 text-emerald-700'],
        'missed' => ['label' => 'Missed — catch up', 'class' => 'bg-amber-100 text-amber-700'],
        'upcoming' => ['label' => 'Upcoming', 'class' => 'bg-gray-100 text-gray-500'],
    ][$selected['status']];
@endphp

<div data-day-detail>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-gray-900">Selected: Day {{ str_pad($selectedDay, 2, '0', STR_PAD_LEFT) }}</h2>
        <div class="flex items-center gap-2">
            @if (! empty($selected['chapters']))
                <span class="text-xs font-medium text-gray-400">~{{ $selected['minutes'] }} min</span>
            @endif
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
        </div>
    </div>

    @if (isset($selected['mock_test']))
        <p class="mt-3 text-sm text-gray-600">{{ $selected['note'] }}</p>
        <a href="{{ route('mock-tests.index') }}" class="mt-4 inline-block rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Go to Mock Tests</a>
    @elseif (empty($selected['chapters']))
        <p class="mt-3 text-sm text-gray-600">{{ $selected['note'] ?? 'Nothing scheduled for this day yet.' }}</p>
    @else
        <ul class="mt-3 space-y-1 text-sm text-gray-500">
            <li>&bull; Today's learning topic — {{ collect($selected['chapters'])->pluck('course_title')->unique()->implode(' + ') }}</li>
            <li>&bull; Read the full lesson and explanation on each chapter page</li>
            <li>&bull; Try the chapter quiz as practice</li>
            <li>&bull; Tick off the daily completion checklist below once you're done</li>
        </ul>
        <div class="mt-4 space-y-3">
            @foreach ($selected['chapters'] as $chapter)
                @include('study-plan.partials.chapter-row', ['chapter' => $chapter])
            @endforeach
        </div>
    @endif
</div>
