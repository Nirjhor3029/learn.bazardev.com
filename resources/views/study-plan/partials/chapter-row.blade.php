<div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-3">
    <div class="min-w-0">
        <a href="{{ route('course.chapter', [$chapter['course'], $chapter['chapter']]) }}" class="truncate text-sm font-medium text-gray-900 hover:text-blue-600">
            {{ $chapter['course_title'] ?? config("course.courses.{$chapter['course']}.title") }} — {{ $chapter['title'] ?? $chapter['chapter'] }}
        </a>
        <p class="text-xs text-gray-400">~{{ $chapter['minutes'] }} min</p>
    </div>

    <form method="POST" action="{{ route('learning.done', [$chapter['course'], $chapter['chapter']]) }}" class="shrink-0">
        @csrf
        <input type="hidden" name="value" value="{{ $chapter['is_done'] ? 0 : 1 }}">
        @if ($chapter['is_done'])
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                <x-icon name="check" class="h-3.5 w-3.5" /> Done
            </button>
        @else
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                Mark done
            </button>
        @endif
    </form>
</div>
