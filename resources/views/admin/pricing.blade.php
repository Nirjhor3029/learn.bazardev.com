<x-layout title="Admin · Course pricing">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <x-admin.nav />
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Course pricing</h1>
        <p class="mt-2 text-sm text-gray-500">
            A course with no row here yet stays open/free, exactly as it is today — the paywall only switches on
            for a course once you uncheck “Free” and save, deliberately, one course at a time. Price is in whole
            BDT. Payment collection is live via pay.bazardev (sandbox mode) — unchecking “Free” here puts a real
            buy button in front of real users. By default only the first chapter stays a free preview once a
            course is paid — open “Free chapters” on any row to pick exactly which chapters stay unlocked.
        </p>

        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-3xl text-left text-sm">
                <thead class="border-b border-gray-100 text-xs tracking-wide text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-medium">Course</th>
                        <th class="px-5 py-3 font-medium">Free?</th>
                        <th class="px-5 py-3 font-medium">Price (BDT)</th>
                        <th class="px-5 py-3 font-medium">In subscription?</th>
                        <th class="px-5 py-3 font-medium">Free chapters</th>
                        <th class="px-5 py-3 font-medium">Last updated</th>
                        <th class="px-5 py-3 text-right font-medium">Save</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($courses as $course)
                        @php
                            $pricing = $course['pricing'];
                            $formId = 'pricing-'.$course['slug'];
                            $defaultFreeSlugs = array_slice(array_keys($course['chapters']), 0, 1);
                            $freeSlugs = $pricing?->free_chapter_slugs ?? $defaultFreeSlugs;
                        @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $course['title'] }}</p>
                                <p class="text-xs text-gray-400">{{ $course['slug'] }} · {{ $course['section'] }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <input form="{{ $formId }}" type="checkbox" name="is_free" value="1" data-field="is_free" @checked($pricing ? $pricing->is_free : true)
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-5 py-3">
                                <input form="{{ $formId }}" type="number" name="price_amount" min="0" max="1000000" step="1" data-field="price_amount"
                                       value="{{ $pricing?->price_amount ?? 0 }}"
                                       class="w-28 rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                            </td>
                            <td class="px-5 py-3">
                                <input form="{{ $formId }}" type="checkbox" name="subscription_eligible" value="1" data-field="subscription_eligible" @checked($pricing?->subscription_eligible ?? true)
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-5 py-3">
                                <button type="button" data-toggle-chapters
                                        class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline">
                                    <span data-chapters-count>{{ count($freeSlugs) }} of {{ count($course['chapters']) }}</span> free
                                    <x-icon name="chevron-down" class="h-3 w-3 transition-transform" data-toggle-chevron />
                                </button>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500" data-updated-cell>
                                @if ($pricing)
                                    {{ $pricing->updated_at->diffForHumans() }}
                                    @if ($pricing->updatedBy)
                                        by {{ $pricing->updatedBy->name }}
                                    @endif
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-500">Not configured — currently open/free</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <form id="{{ $formId }}" data-pricing-form method="POST" action="{{ route('admin.pricing.update', $course['slug']) }}">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <p data-row-error hidden class="mb-1 text-xs font-medium text-rose-600"></p>
                                <x-button form="{{ $formId }}" variant="secondary" data-save-btn class="px-3 py-1.5">Save</x-button>
                            </td>
                        </tr>
                        <tr data-chapters-row hidden>
                            <td colspan="7" class="bg-gray-50 px-5 py-4">
                                <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">
                                    Free preview chapters — anyone can read these, even without buying {{ $course['title'] }}
                                </p>
                                @if (count($course['chapters']) === 0)
                                    <p class="mt-2 text-xs text-gray-400">This course has no readable chapters yet.</p>
                                @else
                                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                                        @foreach ($course['chapters'] as $chapterSlug => $chapter)
                                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                                <input form="{{ $formId }}" type="checkbox" name="free_chapter_slugs[]" value="{{ $chapterSlug }}"
                                                       data-chapter-checkbox @checked(in_array($chapterSlug, $freeSlugs, true))
                                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                {{ $loop->iteration }}. {{ $chapter['title'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <script>
        // Every row saves itself over AJAX, so saving one course never reloads the page and
        // never discards what you've typed into the other rows. Without JavaScript, each
        // form still submits normally (full reload, one course at a time).
        (() => {
            const token = document.querySelector('meta[name="csrf-token"]').content;

            // The "Free chapters" panel for each course sits in its own row right below
            // the main one, collapsed by default, so the table stays readable.
            document.querySelectorAll('[data-toggle-chapters]').forEach((btn) => {
                const chaptersRow = btn.closest('tr').nextElementSibling;
                const chevron = btn.querySelector('[data-toggle-chevron]');

                btn.addEventListener('click', () => {
                    chaptersRow.hidden = !chaptersRow.hidden;
                    chevron.classList.toggle('rotate-180', !chaptersRow.hidden);
                });
            });

            document.querySelectorAll('form[data-pricing-form]').forEach((form) => {
                const row = form.closest('tr');
                const chaptersRow = row.nextElementSibling;
                const saveBtn = row.querySelector('[data-save-btn]');
                const updatedCell = row.querySelector('[data-updated-cell]');
                const errorEl = row.querySelector('[data-row-error]');
                const chaptersCount = row.querySelector('[data-chapters-count]');
                const savedLabel = 'Saved ✓';
                const defaultLabel = saveBtn.textContent;

                form.addEventListener('submit', (e) => {
                    e.preventDefault();

                    const freeChapterSlugs = Array.from(chaptersRow.querySelectorAll('[data-chapter-checkbox]:checked')).map((cb) => cb.value);

                    const payload = {
                        is_free: row.querySelector('[data-field="is_free"]').checked,
                        price_amount: Number(row.querySelector('[data-field="price_amount"]').value),
                        subscription_eligible: row.querySelector('[data-field="subscription_eligible"]').checked,
                        free_chapter_slugs: freeChapterSlugs,
                    };

                    errorEl.hidden = true;
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving…';

                    fetch(form.action, {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify(payload),
                    })
                        .then(async (response) => {
                            const body = await response.json().catch(() => ({}));
                            if (!response.ok) throw body;

                            updatedCell.textContent = `${body.updated_at} by ${body.updated_by}`;
                            chaptersCount.textContent = `${freeChapterSlugs.length} of ${chaptersRow.querySelectorAll('[data-chapter-checkbox]').length}`;
                            saveBtn.textContent = savedLabel;
                            setTimeout(() => (saveBtn.textContent = defaultLabel), 1600);
                        })
                        .catch((body) => {
                            errorEl.textContent = body?.errors?.price_amount?.[0] ?? body?.message ?? 'Could not save — check your connection and try again.';
                            errorEl.hidden = false;
                            saveBtn.textContent = defaultLabel;
                        })
                        .finally(() => {
                            saveBtn.disabled = false;
                        });
                });
            });
        })();
    </script>
</x-layout>
