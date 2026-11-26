<x-layout title="Mock test — Listening">
    <section class="mx-auto max-w-2xl px-4 pt-14 pb-20">
        <p class="text-xs font-semibold tracking-wide text-blue-600 uppercase">Step 1 of 4 &middot; Listening</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Take the real Listening test</h1>
        <p class="mt-3 text-gray-500">We don't host or recreate IELTS Listening audio ourselves — recorded test audio is copyrighted, so synthesizing it with a robot voice would be a poor substitute anyway. Instead, take the real thing for free on British Council's site, then come back here with your score.</p>

        <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <ol class="list-decimal space-y-2 pl-5 text-sm text-gray-700">
                <li>Open British Council's free Listening practice test (opens in a new tab) and listen to all 4 parts, start to finish, just once.</li>
                <li>Mark your answers as you go, then check them against the answer key on their site.</li>
                <li>Come back to this tab and enter how many you got right out of {{ $total }} below.</li>
            </ol>
            <a href="{{ $practiceUrl }}" target="_blank" rel="noopener"
               class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                Open the free Listening test ↗
            </a>
        </div>

        @if ($errors->any())
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('mock-tests.listening.submit', [$attempt->mock_exam, $attempt]) }}" class="mt-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf
            <label for="raw" class="block text-sm font-medium text-gray-700">Your raw score (out of {{ $total }})</label>
            <input type="number" id="raw" name="raw" min="0" max="{{ $total }}" required
                   class="mt-2 w-32 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
            <button type="submit" class="mt-4 block rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save and continue to Reading</button>
        </form>
    </section>
</x-layout>
