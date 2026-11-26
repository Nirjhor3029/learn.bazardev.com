<div class="flex items-center justify-between gap-4 border-b border-stone-100 py-3 last:border-0">
    <div class="min-w-0">
        <p class="text-xs text-stone-400">{{ $chapter['course_title'] ?? config("course.courses.{$chapter['course']}.title") }}</p>
        <a href="{{ route('course.chapter', [$chapter['course'], $chapter['chapter']]) }}" class="text-sm font-medium text-stone-900 hover:text-blue-600">
            {{ $chapter['title'] ?? $chapter['chapter'] }}
        </a>
        <span class="ml-1.5 text-xs text-stone-400">{{ $chapter['minutes'] }} min</span>
    </div>

    <form method="POST" action="{{ route('learning.done', [$chapter['course'], $chapter['chapter']]) }}" class="shrink-0">
        @csrf
        <input type="hidden" name="value" value="{{ $chapter['is_done'] ? 0 : 1 }}">
        @if ($chapter['is_done'])
            <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-white hover:bg-emerald-700" aria-label="Mark as not done">
                <x-icon name="check" class="h-4 w-4" />
            </button>
        @else
            <button type="submit" class="h-7 w-7 rounded-full border-2 border-stone-300 hover:border-blue-500" aria-label="Mark done"></button>
        @endif
    </form>
</div>
