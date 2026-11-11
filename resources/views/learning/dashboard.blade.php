<x-layout title="My Learning">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <p class="text-sm font-semibold text-blue-600">My Learning</p>
        <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
            {{ $stats['done'] > 0 ? 'Welcome back' : 'Welcome' }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }}
        </h1>
        <p class="mt-2 text-sm text-gray-500">Your progress, bookmarks and notes — all in one place. Only you can see this page.</p>

        {{-- The one next step --}}
        <div class="mt-6">
            @if ($resume)
                <x-resume-card :chapter="$resume" :label="$resume['is_next'] ? 'Up next' : 'Continue where you left off'" />
            @elseif ($stats['total'] > 0 && $stats['done'] === $stats['total'])
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                    <p class="font-semibold">You finished every chapter 🎉</p>
                    <p class="mt-0.5 text-sm text-emerald-800">New courses are on the way. Until then, your bookmarks and notes are below for revision.</p>
                </div>
            @elseif ($start)
                <x-resume-card :chapter="$start" label="Start here" action="Start" />
            @endif
        </div>

        {{-- Numbers at a glance --}}
        <dl class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Chapters done', 'value' => $stats['done'], 'of' => $stats['total']],
                ['label' => 'Courses completed', 'value' => $stats['coursesDone'], 'of' => $stats['coursesTotal']],
                ['label' => 'Bookmarks', 'value' => $bookmarks->count(), 'of' => null],
                ['label' => 'Notes', 'value' => $notes->count(), 'of' => null],
            ] as $tile)
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <dt class="text-sm text-gray-500">{{ $tile['label'] }}</dt>
                    <dd class="mt-1 text-2xl font-bold tracking-tight text-gray-900 tabular-nums">
                        {{ $tile['value'] }}@if ($tile['of'] !== null)<span class="text-base font-medium text-gray-400"> / {{ $tile['of'] }}</span>@endif
                    </dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-10 grid gap-10 lg:grid-cols-5">
            {{-- Course progress --}}
            <div class="lg:col-span-3">
                <h2 class="text-lg font-bold tracking-tight">Courses</h2>
                <ul class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                    @foreach ($progress as $slug => $mine)
                        <li>
                            <a href="{{ route('course.show', $slug) }}" class="block px-5 py-4 transition-colors hover:bg-gray-50">
                                <span class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-gray-900">{{ $courses[$slug]['title'] }}</span>
                                    @if ($mine['done'] === $mine['total'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700"><x-icon name="check" class="h-3 w-3" /> Completed</span>
                                    @else
                                        <span class="text-sm text-gray-500 tabular-nums">{{ $mine['done'] }} / {{ $mine['total'] }}</span>
                                    @endif
                                </span>
                                @isset($quizzes[$slug])
                                    <span class="mt-1.5 block text-xs text-gray-500 tabular-nums">Quizzes passed: {{ $quizzes[$slug]['passed'] }} / {{ $quizzes[$slug]['total'] }}</span>
                                @endisset
                                <x-progress :done="$mine['done']" :total="$mine['total']" class="mt-3" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Bookmarks --}}
            <div class="lg:col-span-2" id="bookmarks">
                <h2 class="text-lg font-bold tracking-tight">Bookmarks</h2>
                @if ($bookmarks->isEmpty())
                    <div class="mt-4 rounded-2xl border border-dashed border-gray-200 p-6 text-center">
                        <x-icon name="bookmark" class="mx-auto h-6 w-6 text-gray-300" />
                        <p class="mt-2 text-sm font-medium text-gray-700">No bookmarks yet</p>
                        <p class="mt-1 text-sm text-gray-500">Press <span class="font-medium text-gray-700">Bookmark</span> at the top of a chapter you want to come back to.</p>
                    </div>
                @else
                    <ul class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                        @foreach ($bookmarks as $bookmark)
                            <li>
                                <a href="{{ route('course.chapter', [$bookmark['course'], $bookmark['chapter']]) }}" class="flex items-start gap-3 px-5 py-3.5 transition-colors hover:bg-gray-50">
                                    <x-icon name="bookmark-solid" class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-600" />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-gray-900">{{ $bookmark['title'] }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ $bookmark['course_title'] }} · Chapter {{ $bookmark['number'] }}@if ($bookmark['done']) · <span class="text-emerald-700">Done</span>@endif
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Notes --}}
        <div class="mt-10" id="notes">
            <h2 class="text-lg font-bold tracking-tight">Notes</h2>
            @if ($notes->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-gray-200 p-6 text-center">
                    <x-icon name="pencil" class="mx-auto h-6 w-6 text-gray-300" />
                    <p class="mt-2 text-sm font-medium text-gray-700">No notes yet</p>
                    <p class="mt-1 text-sm text-gray-500">There is a private note box at the end of every chapter. What you write there shows up here.</p>
                </div>
            @else
                <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($notes as $item)
                        <li class="flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    @if ($item['chapter'])
                                        <p class="text-sm font-semibold text-gray-900">{{ $item['chapter']['title'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $item['chapter']['course_title'] }} · Chapter {{ $item['chapter']['number'] }}</p>
                                    @else
                                        <p class="text-sm font-semibold text-gray-900">{{ $item['note']->chapter }}</p>
                                        <p class="text-xs text-gray-500">This chapter is no longer available</p>
                                    @endif
                                </div>
                                <time class="flex-shrink-0 text-xs text-gray-400" datetime="{{ $item['note']->updated_at->toIso8601String() }}">{{ $item['note']->updated_at->diffForHumans() }}</time>
                            </div>
                            <p class="mt-3 line-clamp-6 text-sm leading-relaxed whitespace-pre-line text-gray-700">{{ $item['note']->body }}</p>
                            @if ($item['chapter'])
                                <a href="{{ route('course.chapter', [$item['chapter']['course'], $item['chapter']['chapter']]) }}#note-heading"
                                   class="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-medium text-blue-600 hover:text-blue-700">
                                    Open chapter to read or edit <x-icon name="arrow-right" class="h-4 w-4" />
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Contributions --}}
        <div class="mt-10" id="contributions">
            <h2 class="text-lg font-bold tracking-tight">My contributions</h2>
            @if ($contributions->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-gray-200 p-6 text-center">
                    <x-icon name="bulb" class="mx-auto h-6 w-6 text-gray-300" />
                    <p class="mt-2 text-sm font-medium text-gray-700">Nothing shared yet</p>
                    <p class="mt-1 text-sm text-gray-500">At the end of every chapter you can suggest a helpful link, add a tip, or report a mistake.</p>
                </div>
            @else
                @php
                    $statusTones = [
                        \App\Models\Contribution::PENDING => 'bg-amber-50 text-amber-700',
                        \App\Models\Contribution::APPROVED => 'bg-emerald-50 text-emerald-700',
                        \App\Models\Contribution::REJECTED => 'bg-gray-100 text-gray-600',
                    ];
                    $typeIcons = ['resource' => 'link', 'tip' => 'bulb', 'correction' => 'flag'];
                @endphp
                <ul class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                    @foreach ($contributions as $item)
                        <li class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3 px-5 py-4">
                            <div class="flex min-w-0 flex-1 gap-3">
                                <x-icon :name="$typeIcons[$item->type] ?? 'link'" class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium break-words text-gray-900">{{ $item->title ?? \Illuminate\Support\Str::limit($item->body, 120) }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ $item->typeLabel() }} ·
                                        @if ($item->place)
                                            <a href="{{ route('course.chapter', [$item->place['course'], $item->place['chapter']]) }}#community" class="hover:text-blue-600">{{ $item->place['course_title'] }} · Chapter {{ $item->place['number'] }}</a>
                                        @else
                                            chapter no longer available
                                        @endif
                                        · {{ $item->created_at->diffForHumans() }}
                                    </p>
                                    @if ($item->status === \App\Models\Contribution::REJECTED && $item->admin_note)
                                        <p class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600"><span class="font-medium text-gray-700">Note from the admin:</span> {{ $item->admin_note }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-shrink-0 items-center gap-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTones[$item->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $item->statusLabel() }}</span>
                                <form method="POST" action="{{ route('contributions.destroy', $item) }}"
                                      onsubmit="return confirm('Remove this contribution? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md px-2 py-1 text-xs font-medium text-gray-500 transition-colors hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-100">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-layout>
