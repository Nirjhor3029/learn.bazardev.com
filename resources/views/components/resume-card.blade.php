{{-- "Continue where you left off" — one clear next step. $chapter comes from Learning::resume() / firstChapter(). --}}
@props(['chapter', 'label' => 'Continue where you left off', 'action' => 'Continue'])

<a href="{{ route('course.chapter', [$chapter['course'], $chapter['chapter']]) }}"
   {{ $attributes->merge(['class' => 'group flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-blue-100 bg-white p-5 shadow-sm transition hover:border-blue-200 hover:shadow-md']) }}>
    <span class="flex min-w-0 items-center gap-4">
        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
            <x-icon name="play" class="h-5 w-5" />
        </span>
        <span class="min-w-0">
            <span class="block text-xs font-semibold tracking-wide text-blue-600 uppercase">{{ $label }}</span>
            <span class="mt-0.5 block truncate font-semibold text-gray-900">{{ $chapter['title'] }}</span>
            <span class="block text-sm text-gray-500">{{ $chapter['course_title'] }} · Chapter {{ $chapter['number'] }} of {{ $chapter['total'] }}</span>
        </span>
    </span>
    <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors group-hover:bg-blue-700">
        {{ $action }} <x-icon name="arrow-right" class="h-4 w-4" />
    </span>
</a>
