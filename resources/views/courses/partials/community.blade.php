{{--
    Under every chapter: what readers shared (approved by the admin) and the form to share something.
    Needs: $contributions, $waiting, $course, $slug, $lang, $chapterPath.
--}}
@php
    $bag = $errors->getBag('contribution');
    $type = in_array(old('type'), \App\Models\Contribution::TYPES, true) ? old('type') : 'resource';
    $formOpen = $bag->any() || old('type') !== null;

    $kinds = [
        'resource' => ['icon' => 'link', 'label' => 'A resource', 'text' => 'A link that helped you: video, article, docs.'],
        'tip' => ['icon' => 'bulb', 'label' => 'A tip', 'text' => 'Something you learned the hard way.'],
        'correction' => ['icon' => 'flag', 'label' => 'A mistake', 'text' => 'Something in this chapter is wrong or unclear.'],
    ];

    $detailsLabels = [
        'resource' => 'Why is it useful? (optional)',
        'tip' => 'Your tip',
        'correction' => 'What is wrong, and what should it say?',
    ];
    $field = 'block w-full rounded-lg border bg-white px-3.5 py-2.5 text-base leading-relaxed text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:ring-4 sm:text-sm';
@endphp

<section id="community" class="mt-12 scroll-mt-6" aria-labelledby="community-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="community-heading" class="text-lg font-bold tracking-tight">From the community</h2>
        @if ($contributions->isNotEmpty())
            <p class="text-sm text-gray-500 tabular-nums">{{ $contributions->count() }} {{ \Illuminate\Support\Str::plural('contribution', $contributions->count()) }}</p>
        @endif
    </div>
    <p class="mt-1 text-sm text-gray-500">Links and tips shared by readers. Each one is reviewed before it appears here.</p>

    @if ($contributions->isEmpty())
        <div class="mt-4 rounded-2xl border border-dashed border-gray-200 p-6 text-center">
            <x-icon name="bulb" class="mx-auto h-6 w-6 text-gray-300" />
            <p class="mt-2 text-sm font-medium text-gray-700">Nothing shared for this chapter yet</p>
            <p class="mt-1 text-sm text-gray-500">Found a video or article that made this topic click? Be the first to share it.</p>
        </div>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($contributions as $item)
                @php $isResource = $item->type === 'resource'; @endphp
                <li class="flex gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full {{ $isResource ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600' }}">
                        <x-icon :name="$isResource ? 'link' : 'bulb'" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold tracking-wide uppercase {{ $isResource ? 'text-blue-600' : 'text-amber-700' }}">{{ $item->typeLabel() }}</p>

                        @if ($isResource)
                            {{-- Reader-submitted link: opens in a new tab and passes no ranking or referrer. --}}
                            <a href="{{ $item->url }}" target="_blank" rel="nofollow ugc noopener noreferrer"
                               class="mt-1 inline-block font-semibold break-words text-gray-900 underline decoration-gray-300 underline-offset-4 transition-colors hover:text-blue-700 hover:decoration-blue-300">{{ $item->title }}</a>
                            <span class="ml-1 text-xs whitespace-nowrap text-gray-400">{{ $item->host() }} ↗</span>
                        @endif

                        @if ($item->body)
                            <p class="mt-1 text-sm leading-relaxed break-words whitespace-pre-line text-gray-700">{{ $item->body }}</p>
                        @endif

                        <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 font-semibold text-gray-600" aria-hidden="true">{{ \Illuminate\Support\Str::substr($item->user->initials(), 0, 1) }}</span>
                            <span>Shared by <span class="font-medium text-gray-700">{{ $item->user->name }}</span></span>
                            @if ($item->user->show_email)
                                <span class="text-gray-300" aria-hidden="true">·</span>
                                <span class="break-all">{{ $item->user->email }}</span>
                            @endif
                            <span class="text-gray-300" aria-hidden="true">·</span>
                            <time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->diffForHumans() }}</time>
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- The reader's own suggestions that are still in the queue. --}}
    @if ($waiting->isNotEmpty())
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-sm font-semibold text-amber-900">Waiting for review <span class="font-normal text-amber-800">· only you can see this</span></p>
            <ul class="mt-2 space-y-1.5 text-sm text-amber-900">
                @foreach ($waiting as $item)
                    <li class="flex gap-2">
                        <span class="flex-shrink-0 font-medium">{{ $item->typeLabel() }}:</span>
                        <span class="min-w-0 truncate">{{ $item->title ?? \Illuminate\Support\Str::limit($item->body, 90) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-amber-800">You can follow or remove these in <a href="{{ route('learning.dashboard') }}#contributions" class="font-medium underline underline-offset-2">My Learning</a>.</p>
        </div>
    @endif

    {{-- Share something --}}
    @auth
        @php $me = auth()->user(); @endphp
        <details id="contribute" class="group mt-4 scroll-mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm" @if ($formOpen) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-2xl p-5 transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-100 sm:px-6 [&::-webkit-details-marker]:hidden">
                <span class="min-w-0">
                    <span class="block font-semibold text-gray-900">Share something for this chapter</span>
                    <span class="mt-0.5 block text-sm text-gray-500">Suggest a resource, add a tip, or report a mistake.</span>
                </span>
                <span class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm">
                    <span class="group-open:hidden">Open</span><span class="hidden group-open:inline">Close</span>
                </span>
            </summary>

            <form method="POST" action="{{ route('contributions.store', [$course['slug'], $slug]) }}" class="border-t border-gray-100 p-5 sm:p-6" data-contribution-form>
                @csrf
                <input type="hidden" name="lang" value="{{ $lang }}">

                @if ($bag->has('limit'))
                    <p role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $bag->first('limit') }}</p>
                @endif

                <fieldset>
                    <legend class="text-sm font-medium text-gray-700">What do you want to share?</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        @foreach ($kinds as $value => $kind)
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-gray-200 bg-white p-3.5 transition has-checked:border-blue-500 has-checked:bg-blue-50 has-focus-visible:ring-4 has-focus-visible:ring-blue-100 hover:border-gray-300">
                                <input type="radio" name="type" value="{{ $value }}" class="sr-only" @checked($type === $value)>
                                <x-icon :name="$kind['icon']" class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-500" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900">{{ $kind['label'] }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">{{ $kind['text'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($bag->has('type'))
                        <p class="mt-1.5 text-sm text-rose-600">{{ $bag->first('type') }}</p>
                    @endif
                </fieldset>

                {{-- Only a resource has a title and a link. Without JavaScript both simply stay visible. --}}
                <div class="mt-5 grid gap-5 sm:grid-cols-2" data-only="resource" @if ($type !== 'resource') hidden @endif>
                    <x-form.input name="title" label="Title" bag="contribution" id="contribution_title"
                                  maxlength="{{ \App\Models\Contribution::TITLE_MAX }}" placeholder="Docker networking explained in 15 minutes" />
                    <x-form.input name="url" label="Link" type="url" bag="contribution" id="contribution_url"
                                  maxlength="500" inputmode="url" placeholder="https://…" />
                </div>

                <div class="mt-5">
                    <label for="contribution_details" class="block text-sm font-medium text-gray-700" data-details-label>{{ $detailsLabels[$type] }}</label>
                    <textarea id="contribution_details" name="details" rows="4" maxlength="{{ \App\Models\Contribution::BODY_MAX }}"
                              @if ($bag->has('details')) aria-invalid="true" aria-describedby="contribution_details-error" @endif
                              class="{{ $field }} mt-1.5 {{ $bag->has('details') ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : 'border-gray-200 focus:border-blue-500 focus:ring-blue-100' }}">{{ old('details') }}</textarea>
                    @if ($bag->has('details'))
                        <p id="contribution_details-error" class="mt-1.5 text-sm text-rose-600">{{ $bag->first('details') }}</p>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
                    <p class="max-w-md text-xs leading-relaxed text-gray-500">
                        <span data-not="correction" @if ($type === 'correction') hidden @endif>
                            If approved, it appears here as <span class="font-medium text-gray-700">{{ $me->name }}</span>{{ $me->show_email ? ' with your email ('.$me->email.')' : '. Your email stays hidden' }}.
                            <a href="{{ route('account.edit') }}" class="font-medium text-blue-600 hover:text-blue-700">Change</a>
                        </span>
                        <span data-only="correction" @if ($type !== 'correction') hidden @endif>
                            Mistake reports go only to the admin and are never shown publicly.
                        </span>
                    </p>
                    <x-button>Send for review</x-button>
                </div>
            </form>
        </details>

        <script>
            // Show only the fields that fit the chosen kind. The server checks everything again.
            (() => {
                const form = document.querySelector('form[data-contribution-form]');
                const labels = @json($detailsLabels);
                const title = form.querySelector('#contribution_title');
                const url = form.querySelector('#contribution_url');
                const details = form.querySelector('#contribution_details');

                const paint = () => {
                    const type = form.querySelector('input[name="type"]:checked')?.value ?? 'resource';
                    form.querySelectorAll('[data-only]').forEach((el) => (el.hidden = el.dataset.only !== type));
                    form.querySelectorAll('[data-not]').forEach((el) => (el.hidden = el.dataset.not === type));
                    form.querySelector('[data-details-label]').textContent = labels[type];
                    title.required = url.required = type === 'resource';
                    details.required = type !== 'resource';
                };

                form.querySelectorAll('input[name="type"]').forEach((radio) => radio.addEventListener('change', paint));
                paint();
            })();
        </script>
    @else
        <div id="contribute" class="mt-4 flex scroll-mt-6 flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:px-6">
            <div class="min-w-0">
                <p class="font-semibold text-gray-900">Know a great resource for this chapter?</p>
                <p class="mt-0.5 text-sm text-gray-500">Log in to suggest a link, add a tip, or report a mistake.</p>
            </div>
            <a href="{{ route('login', ['next' => $chapterPath]) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                Log in to share <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    @endauth
</section>
