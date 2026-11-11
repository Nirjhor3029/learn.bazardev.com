@php
    $percent = $certificate->total > 0 ? (int) round($certificate->score / $certificate->total * 100) : 0;
    $verifyUrl = route('certificate.show', $certificate->code);
@endphp

<x-layout :title="'Certificate · '.$certificate->name">
    <style>@media print { @page { size: A4 landscape; margin: 10mm; } }</style>

    <section class="mx-auto max-w-4xl px-4 pt-10 pb-16 print:max-w-none print:p-0">
        {{-- Screen-only actions --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
            @if ($isOwner)
                <a href="{{ route('learning.dashboard') }}#certificates" class="text-sm font-medium text-gray-500 hover:text-blue-600">&larr; My Learning</a>
            @else
                <a href="{{ route('home') }}" class="text-sm font-medium text-gray-500 hover:text-blue-600">&larr; learn.bazardev.com</a>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" data-copy="{{ $verifyUrl }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-100">
                    <x-icon name="link" class="h-4 w-4" /> <span data-copy-label>Copy link</span>
                </button>
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">
                    Print / Save as PDF
                </button>
            </div>
        </div>

        <article class="rounded-2xl border border-gray-100 bg-white p-3 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none" aria-label="Certificate of completion">
            <div class="rounded-xl border-4 border-double border-blue-200 px-6 py-10 text-center sm:px-14 sm:py-14">
                <p class="text-xs font-semibold tracking-widest text-blue-600 uppercase sm:text-sm">DevOps Shikhbo · learn.bazardev.com</p>

                <h1 class="mt-5 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">Certificate of Completion</h1>

                <p class="mt-8 text-gray-500">This certifies that</p>
                <p class="mt-3 text-3xl font-bold tracking-tight break-words text-gray-900 sm:text-4xl">{{ $certificate->name }}</p>

                <p class="mt-8 text-gray-500">passed the final exam of the course</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-blue-700 sm:text-3xl">{{ $certificate->courseTitle() }}</p>
                <p class="mt-2 text-sm text-gray-600 tabular-nums">with a score of {{ $certificate->score }} out of {{ $certificate->total }} ({{ $percent }}%)</p>

                <dl class="mx-auto mt-10 grid max-w-xl gap-6 border-t border-gray-100 pt-6 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium tracking-wide text-gray-400 uppercase">Issued on</dt>
                        <dd class="mt-1 font-semibold text-gray-800">{{ $certificate->created_at->format('j F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium tracking-wide text-gray-400 uppercase">Certificate ID</dt>
                        <dd class="mt-1 font-mono font-semibold tracking-wider text-gray-800">{{ $certificate->code }}</dd>
                    </div>
                </dl>

                <p class="mt-8 text-xs break-all text-gray-500">Check that this certificate is real: <span class="font-medium text-gray-700">{{ $verifyUrl }}</span></p>
                <p class="mx-auto mt-2 max-w-xl text-xs text-gray-400">A course-completion certificate issued by learn.bazardev.com after a final exam. It is not an accredited qualification.</p>
            </div>
        </article>

        {{-- Owner only --}}
        @if ($isOwner)
            <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-5 text-sm shadow-sm print:hidden">
                <p class="font-semibold text-gray-900">Name on this certificate</p>
                @if ($accountName !== null && $accountName !== $certificate->name)
                    <p class="mt-1 text-gray-500">It says <span class="font-medium text-gray-700">{{ $certificate->name }}</span>, but your account name is now <span class="font-medium text-gray-700">{{ $accountName }}</span>.</p>
                    <form method="POST" action="{{ route('certificate.name', $certificate->code) }}" class="mt-3">
                        @csrf
                        <x-button variant="secondary" class="px-3.5 py-2">Use my account name</x-button>
                    </form>
                @else
                    <p class="mt-1 text-gray-500">It is your account name. To print another name, change it in <a href="{{ route('account.edit') }}" class="font-medium text-blue-600 hover:text-blue-700">Account settings</a>, then come back and press “Use my account name”.</p>
                @endif
                <p class="mt-3 text-xs text-gray-400">Anyone who has this page's link can open it and see your name. Your email is never shown. Deleting your account removes the certificate.</p>
            </div>
        @endif
    </section>

    <script>
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const label = button.querySelector('[data-copy-label]');
                try {
                    await navigator.clipboard.writeText(button.dataset.copy);
                    label.textContent = 'Copied';
                } catch (e) {
                    label.textContent = 'Copy failed';
                }
                setTimeout(() => (label.textContent = 'Copy link'), 2000);
            });
        });
    </script>
</x-layout>
