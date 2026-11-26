<x-layout title="Study plan">
    <section class="mx-auto max-w-2xl px-4 pt-14 pb-20">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Your 90-day study plan</h1>
        <p class="mt-3 text-gray-500">A day-by-day schedule across all 6 IELTS courses, paced so each phase builds on the last — no guessing what to study next.</p>

        <div class="mt-8 space-y-3">
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="font-semibold text-gray-900">Days 1-17 — Foundation</p>
                <p class="mt-1 text-sm text-gray-500">Grammar + Vocabulary, a chapter or two a day.</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="font-semibold text-gray-900">Days 18-31 — Comprehension</p>
                <p class="mt-1 text-sm text-gray-500">Reading + Listening strategy, one chapter of each a day.</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="font-semibold text-gray-900">Days 32-57 — Production</p>
                <p class="mt-1 text-sm text-gray-500">Writing + Speaking, slower on purpose — a chapter a day, alternating.</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="font-semibold text-gray-900">Days 58-90 — Confidence</p>
                <p class="mt-1 text-sm text-gray-500">Four full Mock Test sittings, with review days in between for your weak spots.</p>
            </div>
        </div>

        <p class="mt-6 text-sm text-gray-500">Each day's checklist uses the same "mark as done" you already have on every chapter page — ticking something off here and on the chapter itself are the same thing.</p>

        <form method="POST" action="{{ route('study-plan.start') }}" class="mt-6">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Start my 3-month plan</button>
        </form>
    </section>
</x-layout>
