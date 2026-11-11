@php
    use App\Models\Contribution;

    $tabs = [
        Contribution::PENDING => 'Waiting',
        Contribution::APPROVED => 'Approved',
        Contribution::REJECTED => 'Rejected',
    ];
    $tones = [
        'resource' => 'bg-blue-50 text-blue-700',
        'tip' => 'bg-amber-50 text-amber-700',
        'correction' => 'bg-rose-50 text-rose-700',
    ];
    $icons = ['resource' => 'link', 'tip' => 'bulb', 'correction' => 'flag'];
@endphp

<x-layout title="Admin · Contributions">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <x-admin.nav />
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Contributions</h1>
        <p class="mt-2 text-sm text-gray-500">Nothing a reader sends in is public until you approve it. Mistake reports are never shown publicly.</p>

        {{-- Status tabs --}}
        <div class="mt-6 inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
            @foreach ($tabs as $value => $label)
                <a href="{{ route('admin.contributions.index', $value === Contribution::PENDING ? [] : ['status' => $value]) }}"
                   @if ($status === $value) aria-current="page" @endif
                   class="rounded-md px-3 py-1.5 {{ $status === $value ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">
                    {{ $label }} <span class="tabular-nums opacity-70">{{ $counts[$value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        @if ($contributions->isEmpty())
            <div class="mt-4 rounded-2xl border border-dashed border-gray-200 p-10 text-center">
                <x-icon name="check" class="mx-auto h-6 w-6 text-gray-300" />
                <p class="mt-2 text-sm font-medium text-gray-700">
                    {{ $status === Contribution::PENDING ? 'Nothing is waiting for review 🎉' : 'Nothing here yet' }}
                </p>
                @if ($status === Contribution::PENDING)
                    <p class="mt-1 text-sm text-gray-500">New suggestions from readers will show up here.</p>
                @endif
            </div>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($contributions as $item)
                    @php
                        $place = $item->place;
                        $isCorrection = $item->type === 'correction';
                    @endphp
                    <li class="rounded-2xl border border-gray-100 bg-white shadow-sm">
                        <div class="p-5 sm:p-6">
                            {{-- What and where --}}
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tones[$item->type] ?? 'bg-gray-100 text-gray-600' }}">
                                    <x-icon :name="$icons[$item->type] ?? 'link'" class="h-3 w-3" /> {{ $item->typeLabel() }}
                                </span>
                                @if ($place)
                                    <a href="{{ route('course.chapter', [$place['course'], $place['chapter']]) }}#community" class="font-medium text-gray-700 hover:text-blue-600">
                                        {{ $place['course_title'] }} · Chapter {{ $place['number'] }} — {{ $place['title'] }}
                                    </a>
                                @else
                                    <span class="text-gray-400">{{ $item->course }}/{{ $item->chapter }} (chapter no longer available)</span>
                                @endif
                                <time class="ml-auto text-xs text-gray-400" datetime="{{ $item->created_at->toIso8601String() }}" title="{{ $item->created_at->toDayDateTimeString() }}">{{ $item->created_at->diffForHumans() }}</time>
                            </div>

                            {{-- The content, exactly as it would be published --}}
                            <div class="mt-4">
                                @if ($item->type === 'resource')
                                    <p class="font-semibold text-gray-900">{{ $item->title }}</p>
                                    <a href="{{ $item->url }}" target="_blank" rel="nofollow ugc noopener noreferrer"
                                       class="mt-0.5 inline-block text-sm break-all text-blue-600 underline decoration-blue-200 underline-offset-4 hover:text-blue-700">{{ $item->url }} ↗</a>
                                @endif
                                @if ($item->body)
                                    <p class="mt-2 text-sm leading-relaxed break-words whitespace-pre-line text-gray-700">{{ $item->body }}</p>
                                @endif
                            </div>

                            {{-- Who --}}
                            <div class="mt-4 flex items-center gap-3">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">{{ $item->user->initials() }}</span>
                                <div class="min-w-0 text-sm">
                                    <p class="truncate font-medium text-gray-900">{{ $item->user->name }}</p>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ $item->user->email }}
                                        · {{ $item->user->show_email ? 'email will be shown publicly' : 'email stays hidden' }}
                                    </p>
                                </div>
                            </div>

                            @if ($item->status !== Contribution::PENDING)
                                <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                    {{ $item->status === Contribution::APPROVED ? ($isCorrection ? 'Resolved' : 'Approved') : 'Rejected' }}
                                    @if ($item->reviewer) by {{ $item->reviewer->name }} @endif
                                    @if ($item->reviewed_at) · {{ $item->reviewed_at->diffForHumans() }} @endif
                                    @if ($item->admin_note) <span class="mt-1 block text-gray-700">Reason given to the sender: {{ $item->admin_note }}</span> @endif
                                </p>
                            @endif
                        </div>

                        {{-- Decide --}}
                        <div class="flex flex-wrap items-start gap-3 border-t border-gray-100 px-5 py-4 sm:px-6">
                            @if ($item->status !== Contribution::APPROVED)
                                <form method="POST" action="{{ route('admin.contributions.approve', $item) }}">
                                    @csrf
                                    <x-button class="px-3.5 py-2">
                                        <x-icon name="check" class="h-4 w-4" /> {{ $isCorrection ? 'Mark as resolved' : 'Approve & publish' }}
                                    </x-button>
                                </form>
                            @endif

                            @if ($item->status !== Contribution::REJECTED)
                                <details class="group min-w-0 flex-1">
                                    <summary class="inline-flex cursor-pointer list-none items-center rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-100 [&::-webkit-details-marker]:hidden">
                                        {{ $item->status === Contribution::APPROVED ? ($isCorrection ? 'Reopen as rejected…' : 'Unpublish…') : 'Reject…' }}
                                    </summary>
                                    <form method="POST" action="{{ route('admin.contributions.reject', $item) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                        @csrf
                                        <div class="min-w-0 flex-1">
                                            <label for="note-{{ $item->id }}" class="block text-xs font-medium text-gray-600">Reason (optional — the sender will see it)</label>
                                            <input type="text" id="note-{{ $item->id }}" name="admin_note" maxlength="500" placeholder="e.g. The link is behind a paywall"
                                                   class="mt-1 block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                                        </div>
                                        <x-button variant="danger" class="px-3.5 py-2">Reject</x-button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-6">{{ $contributions->links() }}</div>
    </section>
</x-layout>
