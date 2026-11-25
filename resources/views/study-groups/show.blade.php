<x-layout :title="$group->name">
    <section class="mx-auto max-w-5xl px-4 pt-14 pb-6">
        <p class="text-sm font-semibold text-blue-600"><a href="{{ route('study-groups.index') }}" class="hover:underline">&larr; All groups</a></p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $group->name }}</h1>
        <p class="mt-2 text-gray-500">{{ $group->courseTitle() }} &middot; {{ $courseProgress['done'] }}/{{ $courseProgress['total'] }} chapters done ({{ $courseProgress['percent'] }}%)</p>
    </section>

    @if (session('status'))
        <section class="mx-auto max-w-5xl px-4 pb-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        </section>
    @endif

    @if ($errors->any())
        <section class="mx-auto max-w-5xl px-4 pb-4">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto grid max-w-5xl gap-6 px-4 pb-20 sm:grid-cols-3">
        {{-- Members + progress --}}
        <div class="sm:col-span-1">
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-700">Members</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($members as $row)
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium text-gray-900">{{ $row['member']->user->name }}</span>
                                @if ($row['member']->isOwner())
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Owner</span>
                                @elseif ($isOwner)
                                    <form method="POST" action="{{ route('study-groups.members.remove', [$group, $row['member']->user]) }}" onsubmit="return confirm('Remove {{ $row['member']->user->name }} from the group?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-gray-400 hover:text-red-600">Remove</button>
                                    </form>
                                @endif
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <x-progress :done="$row['progress']['done']" :total="$row['progress']['total']" class="flex-1" />
                                <span class="text-xs text-gray-400 tabular-nums">{{ $row['progress']['percent'] }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($isOwner)
                <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-700">Invite code</h2>
                    <p class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-center font-mono text-lg tracking-widest text-gray-900">{{ $group->invite_code }}</p>
                    <form method="POST" action="{{ route('study-groups.regenerate-code', $group) }}" onsubmit="return confirm('Generate a new code? The old one will stop working.')" class="mt-2">
                        @csrf
                        <button type="submit" class="w-full rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50">Regenerate code</button>
                    </form>

                    <form method="POST" action="{{ route('study-groups.rename', $group) }}" class="mt-4 border-t border-gray-100 pt-4">
                        @csrf @method('PATCH')
                        <label class="text-xs font-medium text-gray-500">Rename group</label>
                        <input type="text" name="name" value="{{ $group->name }}" required maxlength="80"
                               class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
                        <button type="submit" class="mt-2 w-full rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800">Save</button>
                    </form>

                    <form method="POST" action="{{ route('study-groups.destroy', $group) }}" onsubmit="return confirm('Delete this group for everyone? This cannot be undone.')" class="mt-4 border-t border-gray-100 pt-4">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Delete group</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('study-groups.leave', $group) }}" onsubmit="return confirm('Leave this group?')" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-500 hover:bg-gray-50">Leave group</button>
                </form>
            @endif
        </div>

        {{-- Questions & comments --}}
        <div class="sm:col-span-2">
            <form method="POST" action="{{ route('study-groups.posts.store', $group) }}" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="text-sm font-semibold text-gray-700">Ask a question or share a comment</h2>
                <div class="mt-3 flex gap-4 text-sm">
                    <label class="flex items-center gap-1.5"><input type="radio" name="type" value="question" checked> Question</label>
                    <label class="flex items-center gap-1.5"><input type="radio" name="type" value="comment"> Comment</label>
                </div>
                <textarea name="body" rows="3" required maxlength="2000" placeholder="What's on your mind?"
                          class="mt-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400"></textarea>
                <button type="submit" class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Post</button>
            </form>

            <div class="mt-6 space-y-4">
                @forelse ($posts as $post)
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="font-medium text-gray-900">{{ $post->user->name }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $post->isQuestion() ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600' }}">{{ $post->typeLabel() }}</span>
                            </div>
                            @if ($post->user_id === auth()->id() || $isOwner)
                                <form method="POST" action="{{ route('study-groups.posts.destroy', [$group, $post]) }}" onsubmit="return confirm('Remove this post?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-gray-400 hover:text-red-600">Remove</button>
                                </form>
                            @endif
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $post->body }}</p>

                        <div class="mt-3 space-y-3 border-t border-gray-100 pt-3">
                            @foreach ($post->replies as $reply)
                                <div class="pl-4">
                                    <span class="text-sm font-medium text-gray-900">{{ $reply->user->name }}</span>
                                    <p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $reply->body }}</p>
                                </div>
                            @endforeach

                            <form method="POST" action="{{ route('study-groups.posts.store', $group) }}" class="flex gap-2">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $post->id }}">
                                <input type="text" name="body" required maxlength="2000" placeholder="Reply&hellip;"
                                       class="flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
                                <button type="submit" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">Reply</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No questions or comments yet &mdash; be the first.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layout>
