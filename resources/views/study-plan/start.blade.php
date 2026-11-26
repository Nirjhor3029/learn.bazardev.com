<x-layout title="Study plan">
    <section class="mx-auto max-w-xl px-4 pt-14 pb-24">
        <p class="text-6xl font-black tracking-tight text-stone-900">90</p>
        <h1 class="mt-1 text-2xl font-bold text-stone-900">days to get exam-ready</h1>
        <p class="mt-3 text-stone-500">One schedule across all six IELTS courses, paced so each phase builds on the last — you won't have to guess what to study next.</p>

        <div class="mt-8 space-y-5 border-l-2 border-stone-200 pl-5">
            <div>
                <p class="text-sm font-semibold text-stone-900">Days 1&ndash;30 &mdash; Foundation</p>
                <p class="text-sm text-stone-500">Grammar and vocabulary, a chapter or two a day.</p>
            </div>
            <div>
                <p class="text-sm font-semibold text-stone-900">Days 31&ndash;60 &mdash; Technique</p>
                <p class="text-sm text-stone-500">Reading and listening strategy, then writing and speaking practice.</p>
            </div>
            <div>
                <p class="text-sm font-semibold text-stone-900">Days 61&ndash;90 &mdash; Mock exams</p>
                <p class="text-sm text-stone-500">Four full Mock Tests, with review days in between for whatever's shaky.</p>
            </div>
        </div>

        <p class="mt-6 text-sm text-stone-400">Marking a chapter done here is the same checkbox you already have on the chapter page — there's nothing new to keep in sync.</p>

        <form method="POST" action="{{ route('study-plan.start') }}" class="mt-8">
            @csrf
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700">Start day 1</button>
        </form>
    </section>
</x-layout>
