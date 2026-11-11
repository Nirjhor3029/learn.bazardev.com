<x-layout title="Admin · Users">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <p class="text-sm font-semibold text-blue-600">Admin</p>
        <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Users</h1>
        <p class="mt-2 text-sm text-gray-500">Approve an account to mark it verified without the email link.</p>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            {{-- Filter tabs --}}
            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                <a href="{{ route('admin.users.index', array_filter(['q' => $search])) }}"
                   class="rounded-md px-3 py-1.5 {{ $filter === 'all' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">
                    All <span class="tabular-nums opacity-70">{{ $totalCount }}</span>
                </a>
                <a href="{{ route('admin.users.index', array_filter(['filter' => 'pending', 'q' => $search])) }}"
                   class="rounded-md px-3 py-1.5 {{ $filter === 'pending' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">
                    Waiting for approval <span class="tabular-nums opacity-70">{{ $pendingCount }}</span>
                </a>
            </div>

            {{-- Search --}}
            <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-2">
                @if ($filter === 'pending')
                    <input type="hidden" name="filter" value="pending">
                @endif
                <label for="q" class="sr-only">Search users</label>
                <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Search name or email"
                       class="w-56 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                <x-button variant="secondary" class="px-3 py-2">Search</x-button>
            </form>
        </div>

        <div class="mt-4 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-2xl text-left text-sm">
                <thead class="border-b border-gray-100 text-xs tracking-wide text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-medium">User</th>
                        <th class="px-5 py-3 font-medium">Joined</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">{{ $user->initials() }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900">
                                            {{ $user->name }}
                                            @if ($user->is_admin)
                                                <span class="ml-1 rounded-full bg-gray-900 px-2 py-0.5 text-xs font-medium text-white">Admin</span>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-500" title="{{ $user->created_at->toDayDateTimeString() }}">{{ $user->created_at->diffForHumans() }}</td>
                            <td class="px-5 py-3">
                                @if ($user->hasVerifiedEmail())
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Verified</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Waiting</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @unless ($user->hasVerifiedEmail())
                                    <form method="POST" action="{{ route('admin.users.verify', $user) }}">
                                        @csrf
                                        <x-button class="px-3 py-1.5">Approve</x-button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500">
                                @if ($search !== '')
                                    No users match “{{ $search }}”.
                                @elseif ($filter === 'pending')
                                    Nobody is waiting for approval. 🎉
                                @else
                                    No users yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $users->links() }}</div>
    </section>
</x-layout>
