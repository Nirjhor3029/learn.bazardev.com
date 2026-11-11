@php
    $isDone = (bool) $state?->completed_at;
    $isBookmarked = (bool) $state?->bookmarked_at;
    $langQuery = $lang === 'bn' ? '?lang=bn' : '';
    $chapterPath = '/'.$course['slug'].'/'.$slug.$langQuery;
    $totalChapters = count($course['chapters']);
@endphp

<x-layout :title="$title">
    <div class="mx-auto flex max-w-6xl gap-10 px-4 pt-10 pb-20">
        <aside class="hidden w-56 flex-shrink-0 lg:block">
            <div class="sticky top-6">
                <a href="{{ route('course.show', $course['slug']) }}" class="text-sm font-semibold text-gray-700 hover:text-blue-600">&larr; {{ $course['title'] }}</a>

                @if ($progress)
                    <div class="mt-3">
                        <x-progress :done="$progress['done']" :total="$progress['total']" live />
                        <p class="mt-1.5 text-xs text-gray-500 tabular-nums" data-progress-text>{{ $progress['done'] }} of {{ $progress['total'] }} chapters done</p>
                    </div>
                @endif

                <ol class="mt-4 space-y-1 text-sm">
                    @foreach ($course['chapters'] as $chSlug => $ch)
                        @php
                            $isCurrent = $chSlug === $slug;
                            $available = $ch['status'] === 'available';
                            $chDone = in_array($chSlug, $done, true);
                        @endphp
                        <li>
                            <a href="{{ $available ? route('course.chapter', [$course['slug'], $chSlug]) : '#' }}"
                               @if ($isCurrent) aria-current="page" @endif
                               class="flex gap-2 rounded-md px-2 py-1.5
                                      {{ $isCurrent ? 'bg-blue-50 font-semibold text-blue-700' : ($available ? 'text-gray-500 hover:bg-gray-100' : 'text-gray-300') }}">
                                <span class="w-4 flex-shrink-0 text-center tabular-nums">
                                    @if ($isCurrent && $state)
                                        {{-- The current chapter can flip between number and tick without a reload. --}}
                                        <span data-done-off @if ($isDone) hidden @endif>{{ $loop->iteration }}.</span>
                                        <span data-done-on @if (! $isDone) hidden @endif><x-icon name="check" class="mt-0.5 inline h-4 w-4 text-emerald-600" /><span class="sr-only">Done:</span></span>
                                    @elseif ($chDone)
                                        <x-icon name="check" class="mt-0.5 inline h-4 w-4 text-emerald-600" /><span class="sr-only">Done:</span>
                                    @else
                                        {{ $loop->iteration }}.
                                    @endif
                                </span>
                                <span>{{ $ch['title'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>

        <article class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-medium text-blue-600">
                    {{ $course['title'] }} <span class="text-gray-300">·</span> <span class="text-gray-500">Chapter {{ $number }} of {{ $totalChapters }}</span>
                </p>

                <div class="flex items-center gap-2">
                    @auth
                        <form method="POST" action="{{ route('learning.bookmark', [$course['slug'], $slug]) }}" data-toggle-form="bookmark">
                            @csrf
                            <input type="hidden" name="value" value="{{ $isBookmarked ? 0 : 1 }}">
                            <button type="submit" aria-pressed="{{ $isBookmarked ? 'true' : 'false' }}"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-100">
                                <span data-bookmark-off class="inline-flex items-center gap-1.5" @if ($isBookmarked) hidden @endif>
                                    <x-icon name="bookmark" class="h-4 w-4" /> Bookmark
                                </span>
                                <span data-bookmark-on class="inline-flex items-center gap-1.5 text-blue-600" @if (! $isBookmarked) hidden @endif>
                                    <x-icon name="bookmark-solid" class="h-4 w-4" /> Bookmarked
                                </span>
                            </button>
                        </form>
                    @endauth

                    @if ($has_bn)
                        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                            <a href="{{ route('course.chapter', [$course['slug'], $slug]) }}"
                               class="rounded-md px-3 py-1 {{ $lang === 'en' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">EN</a>
                            <a href="{{ route('course.chapter', [$course['slug'], $slug]) }}?lang=bn"
                               class="rounded-md px-3 py-1 {{ $lang === 'bn' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">বাংলা</a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="markdown-body mt-2" lang="{{ $lang }}">
                {!! $html !!}
            </div>

            @auth
                {{-- Mark as done --}}
                <section class="mt-12 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6" aria-label="Your progress">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">
                                <span data-done-off @if ($isDone) hidden @endif>Finished this chapter?</span>
                                <span data-done-on @if (! $isDone) hidden @endif>Nice work — chapter completed 🎉</span>
                            </p>
                            <p class="mt-0.5 text-sm text-gray-500 tabular-nums">
                                <span data-progress-text>{{ $progress['done'] }} of {{ $progress['total'] }} chapters done</span> in {{ $course['title'] }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('learning.done', [$course['slug'], $slug]) }}" data-toggle-form="done">
                            @csrf
                            <input type="hidden" name="value" value="{{ $isDone ? 0 : 1 }}">
                            <button type="submit" data-done-off
                                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200" @if ($isDone) hidden @endif>
                                <x-icon name="check" class="h-4 w-4" /> Mark as done
                            </button>
                            <button type="submit" data-done-on
                                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100" @if (! $isDone) hidden @endif>
                                <x-icon name="check" class="h-4 w-4" /> Done <span class="font-normal text-emerald-600">· Undo</span>
                            </button>
                        </form>
                    </div>

                    <x-progress :done="$progress['done']" :total="$progress['total']" live class="mt-4" />
                </section>

                {{-- Private note --}}
                <section class="mt-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="note-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="note-heading" class="flex items-center gap-2 font-semibold text-gray-900">
                            <x-icon name="pencil" class="h-4 w-4 text-gray-400" />
                            My notes
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                <x-icon name="lock" class="h-3 w-3" /> Only you can see this
                            </span>
                        </h2>
                        <p class="text-xs text-gray-400" data-note-status role="status" aria-live="polite">{{ $note ? 'Saved' : '' }}</p>
                    </div>

                    <form method="POST" action="{{ route('learning.note', [$course['slug'], $slug]) }}" class="mt-3" data-note-form>
                        @csrf
                        @method('PUT')
                        <label for="note-body" class="sr-only">Your private note for this chapter</label>
                        <textarea id="note-body" name="body" rows="5" maxlength="{{ \App\Models\Note::MAX_LENGTH }}"
                                  placeholder="What do you want to remember from this chapter? A command, a mistake you made, a question to look up…"
                                  class="block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-base leading-relaxed text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 sm:text-sm">{{ old('body', $note?->body) }}</textarea>
                        @error('body')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-gray-500">Saves by itself while you type. Find all your notes in <a href="{{ route('learning.dashboard') }}#notes" class="font-medium text-blue-600 hover:text-blue-700">My Learning</a>.</p>
                            <x-button variant="secondary" class="px-3 py-1.5">Save note</x-button>
                        </div>
                    </form>
                </section>
            @else
                {{-- Reading is free; an account only remembers things. --}}
                <section class="mt-12 rounded-2xl border border-blue-100 bg-blue-50 p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">Finished this chapter? Keep track of it.</p>
                            <p class="mt-0.5 text-sm text-gray-600">A free account lets you mark chapters as done, bookmark them and keep private notes.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('login', ['next' => $chapterPath]) }}"
                               class="rounded-lg px-3 py-2.5 text-sm font-semibold text-blue-700 transition-colors hover:bg-blue-100">Log in</a>
                            <a href="{{ route('register', ['next' => $chapterPath]) }}"
                               class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">Sign up free</a>
                        </div>
                    </div>
                </section>
            @endauth

            <div class="mt-10 flex items-center justify-between gap-4 border-t border-gray-100 pt-6 text-sm">
                @if ($prev)
                    <a href="{{ route('course.chapter', [$course['slug'], $prev['slug']]) }}{{ $langQuery }}" class="text-gray-500 hover:text-blue-600">&larr; {{ $prev['title'] }}</a>
                @else
                    <span></span>
                @endif

                @if ($next && $next['status'] === 'available')
                    <a href="{{ route('course.chapter', [$course['slug'], $next['slug']]) }}{{ $langQuery }}" class="text-right font-medium text-blue-600 hover:text-blue-700">{{ $next['title'] }} &rarr;</a>
                @elseif ($next)
                    <span class="text-gray-300">{{ $next['title'] }} (soon)</span>
                @endif
            </div>

            <a href="{{ $github_url }}" target="_blank" rel="noopener" class="mt-6 inline-block text-xs text-gray-400 hover:text-gray-600">View this chapter on GitHub →</a>
        </article>
    </div>

    {{-- Shown briefly when a click could not be saved. --}}
    <div data-toast role="alert"
         class="fixed inset-x-4 bottom-4 z-50 mx-auto hidden max-w-sm rounded-xl bg-gray-900 px-4 py-3 text-center text-sm text-white shadow-lg"></div>

    @auth
        <script>
            // Done / bookmark / notes without reloading the page. Every form also works with JavaScript off.
            (() => {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const toast = document.querySelector('[data-toast]');
                let toastTimer;

                const send = (url, method, data) => fetch(url, {
                    method,
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(data),
                }).then((response) => {
                    if (!response.ok) throw new Error(response.status);
                    return response.json();
                });

                const complain = (status) => {
                    toast.textContent = status === '419' || status === '401'
                        ? 'Your session expired. Reload the page and try again.'
                        : 'Could not save that. Check your connection and try again.';
                    toast.classList.remove('hidden');
                    clearTimeout(toastTimer);
                    toastTimer = setTimeout(() => toast.classList.add('hidden'), 5000);
                };

                // Flip everything on the page that depends on one state ("done" or "bookmark").
                // Uses the hidden attribute (not a class) so it always wins over layout classes.
                const paint = (name, on) => {
                    document.querySelectorAll(`[data-${name}-on]`).forEach((el) => (el.hidden = !on));
                    document.querySelectorAll(`[data-${name}-off]`).forEach((el) => (el.hidden = on));
                    document.querySelectorAll(`form[data-toggle-form="${name}"]`).forEach((form) => {
                        form.querySelector('input[name="value"]').value = on ? '0' : '1';
                        form.querySelectorAll('button[aria-pressed]').forEach((b) => b.setAttribute('aria-pressed', on ? 'true' : 'false'));
                    });
                };

                const paintProgress = ({ done, total, percent }) => {
                    document.querySelectorAll('[data-progress-text]').forEach((el) => {
                        el.textContent = `${done} of ${total} chapters done`;
                    });
                    document.querySelectorAll('[data-progress]').forEach((bar) => bar.setAttribute('aria-valuenow', done));
                    document.querySelectorAll('[data-progress-fill]').forEach((fill) => {
                        fill.style.width = `${percent}%`;
                        fill.classList.toggle('bg-emerald-500', done >= total);
                        fill.classList.toggle('bg-blue-600', done < total);
                    });
                };

                document.querySelectorAll('form[data-toggle-form]').forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        event.preventDefault();

                        const name = form.dataset.toggleForm;
                        const on = form.querySelector('input[name="value"]').value === '1';
                        const buttons = form.querySelectorAll('button');
                        const focused = form.contains(document.activeElement);

                        paint(name, on); // show the result right away, undo it if saving fails
                        buttons.forEach((b) => (b.disabled = true));

                        send(form.action, 'POST', { value: on })
                            .then((data) => data.progress && paintProgress(data.progress))
                            .catch((error) => { paint(name, !on); complain(error.message); })
                            .finally(() => {
                                buttons.forEach((b) => (b.disabled = false));
                                // The clicked button was swapped for its twin; keep keyboard focus there.
                                if (focused) [...buttons].find((b) => !b.hidden)?.focus();
                            });
                    });
                });

                // Notes: save a moment after typing stops, and when leaving the box or the page.
                const noteForm = document.querySelector('form[data-note-form]');
                if (noteForm) {
                    const area = noteForm.querySelector('textarea');
                    const status = document.querySelector('[data-note-status]');
                    let saved = area.value;
                    let timer;

                    const say = (text, bad = false) => {
                        status.textContent = text;
                        status.classList.toggle('text-rose-600', bad);
                        status.classList.toggle('text-gray-400', !bad);
                    };

                    const save = () => {
                        clearTimeout(timer);
                        const body = area.value;
                        if (body === saved) return;

                        say('Saving…');
                        send(noteForm.action, 'PUT', { body })
                            .then(() => {
                                saved = body;
                                if (area.value === body) say(body.trim() === '' ? 'Note removed' : 'Saved');
                                else timer = setTimeout(save, 1200);
                            })
                            .catch((error) => say(error.message === '422'
                                ? 'This note is too long to save'
                                : 'Not saved — check your connection', true));
                    };

                    area.addEventListener('input', () => {
                        say('Typing…');
                        clearTimeout(timer);
                        timer = setTimeout(save, 1200);
                    });
                    area.addEventListener('blur', save);
                    window.addEventListener('pagehide', save);
                    noteForm.addEventListener('submit', (event) => {
                        event.preventDefault();
                        if (area.value === saved) say(saved.trim() === '' ? '' : 'Saved');
                        save();
                    });
                }
            })();
        </script>
    @endauth
</x-layout>
