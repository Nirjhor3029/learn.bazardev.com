<x-layout title="Study groups">
    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Study groups</h1>
        <p class="mt-3 max-w-2xl text-gray-500">Form a small group with the people you're studying with — see each other's progress, ask questions, and comment. Nobody can find your group without the invite code.</p>
    </section>

    @if ($errors->any())
        <section class="mx-auto max-w-5xl px-4 pb-4">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-5xl px-4 pb-10">
        @if ($groups->isEmpty())
            <p class="text-sm text-gray-400">You're not in a study group yet.</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($groups as $row)
                    <a href="{{ route('study-groups.show', $row['group']) }}"
                       class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-blue-200 hover:shadow-md">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $row['group']->name }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $row['group']->courseTitle() }}</p>
                        <p class="mt-3 text-xs font-medium text-gray-400">{{ $row['memberCount'] }} {{ \Illuminate\Support\Str::plural('member', $row['memberCount']) }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @unless ($atLimit)
        <section class="mx-auto max-w-5xl px-4 pb-20">
            <div class="grid gap-4 sm:grid-cols-2">
                <form method="POST" action="{{ route('study-groups.store') }}" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    @csrf
                    <h2 class="font-semibold text-gray-900">Create a group</h2>
                    <div class="mt-4 space-y-3">
                        <input type="text" name="name" placeholder="Group name" required maxlength="80"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
                        <select name="course" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
                            <option value="" disabled selected>Choose a course</option>
                            @foreach ($courses as $slug => $course)
                                <option value="{{ $slug }}">{{ $course['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="mt-4 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create group</button>
                </form>

                <form method="POST" action="{{ route('study-groups.join') }}" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    @csrf
                    <h2 class="font-semibold text-gray-900">Join with a code</h2>
                    <p class="mt-1 text-sm text-gray-400">Ask whoever created the group for their invite code.</p>
                    <input type="text" name="code" placeholder="Invite code" required maxlength="10"
                           class="mt-4 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
                    <button type="submit" class="mt-4 w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Join group</button>
                </form>
            </div>
        </section>
    @endunless
</x-layout>
