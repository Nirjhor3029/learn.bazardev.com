<x-layout title="Study plan">
    <section class="mx-auto max-w-3xl px-4 pt-10 pb-20">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Day {{ $currentDay }} of {{ $totalDays }}</h1>
            <p class="text-sm font-medium text-gray-500 tabular-nums">{{ $progress['done'] }}/{{ $progress['total'] }} chapters done</p>
        </div>
        <x-progress :done="$progress['done']" :total="$progress['total']" class="mt-3" />

        @if ($finished)
            <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800">
                You've reached the end of the 90-day plan 🎉 Keep repeating the Mock Test and reviewing weak chapters until you're consistently hitting your target band, then book the real exam.
            </div>
        @endif

        {{-- Today --}}
        <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-gray-900">Today</h2>
                @if (! empty($today['chapters']))
                    <span class="text-xs font-medium text-gray-400">~{{ $todayMinutes }} minutes</span>
                @endif
            </div>

            @if (isset($today['mock_test']))
                <p class="mt-2 text-sm text-gray-600">{{ $today['note'] }}</p>
                <a href="{{ route('mock-tests.index') }}" class="mt-4 inline-block rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Go to Mock Tests</a>
            @elseif (empty($today['chapters']))
                <p class="mt-2 text-sm text-gray-600">{{ $today['note'] }}</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($today['chapters'] as $chapter)
                        @include('study-plan.partials.chapter-row', ['chapter' => $chapter])
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Catch up --}}
        @if (count($catchUp) > 0)
            <div class="mt-6">
                <h2 class="text-sm font-semibold tracking-wide text-amber-600 uppercase">Catch up — not finished yet</h2>
                <div class="mt-3 space-y-3">
                    @foreach ($catchUp as $day)
                        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5">
                            <p class="text-sm font-semibold text-amber-800">Day {{ $day['day'] }}</p>
                            <div class="mt-3 space-y-3">
                                @foreach ($day['chapters'] as $chapter)
                                    @include('study-plan.partials.chapter-row', ['chapter' => $chapter])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Full plan overview --}}
        <details class="mt-8 rounded-2xl border border-gray-100 bg-white p-5">
            <summary class="cursor-pointer text-sm font-semibold text-gray-900">See the full 90-day plan</summary>
            <div class="mt-4 max-h-96 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <tbody>
                        @foreach ($overview as $row)
                            <tr class="border-t border-gray-100 {{ $row['day'] === $currentDay ? 'bg-blue-50' : '' }}">
                                <td class="py-1.5 pr-3 text-gray-400 tabular-nums">Day {{ $row['day'] }}</td>
                                <td class="py-1.5 {{ $row['is_mock'] ? 'font-medium text-blue-700' : 'text-gray-700' }}">{{ $row['label'] }}</td>
                                <td class="py-1.5 text-right">
                                    @if ($row['done'])
                                        <span class="text-emerald-600">✓</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </section>
</x-layout>
