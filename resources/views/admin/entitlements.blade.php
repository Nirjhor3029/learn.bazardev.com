<x-layout title="Admin · Access grants">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <x-admin.nav />
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Access grants</h1>
        <p class="mt-2 text-sm text-gray-500">
            Manually give a user free access to a course, a course's terminal add-on, or an all-access subscription — no payment involved. Every grant needs a note.
        </p>

        {{-- Grant form --}}
        <div class="mt-6 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Grant access</h2>

            <form method="GET" action="{{ route('admin.entitlements.index') }}" class="mt-3 flex gap-2">
                <label for="q" class="sr-only">Search users</label>
                <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Search name or email to find a user"
                       class="w-72 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                <x-button variant="secondary" class="px-3 py-2">Search</x-button>
            </form>

            @if ($search !== '')
                <div class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-100">
                    @forelse ($users as $user)
                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.entitlements.store') }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <select name="product_type" class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm">
                                    <option value="course">Course</option>
                                    <option value="terminal">Terminal add-on</option>
                                    <option value="subscription_all_access">All-access subscription</option>
                                </select>
                                <select name="product_key" class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm">
                                    @foreach ($courses as $slug => $course)
                                        <option value="{{ $slug }}">{{ $course['title'] }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="grant_note" required placeholder="Why (required)"
                                       class="w-40 rounded-lg border border-gray-200 px-2 py-1.5 text-sm">
                                <x-button class="px-3 py-1.5">Grant</x-button>
                            </form>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-500">No users match “{{ $search }}”.</p>
                    @endforelse
                </div>
            @endif
        </div>

        {{-- Recent grants / history --}}
        <h2 class="mt-8 text-sm font-semibold text-gray-900">All entitlements (most recent first)</h2>
        <div class="mt-3 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-3xl text-left text-sm">
                <thead class="border-b border-gray-100 text-xs tracking-wide text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-medium">User</th>
                        <th class="px-5 py-3 font-medium">Product</th>
                        <th class="px-5 py-3 font-medium">Source</th>
                        <th class="px-5 py-3 font-medium">Note</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recent as $entitlement)
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $entitlement->user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $entitlement->user->email }}</p>
                            </td>
                            <td class="px-5 py-3">
                                {{ $entitlement->product_type }}
                                @if ($entitlement->product_key)
                                    <span class="text-gray-400">· {{ $entitlement->product_key }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                {{ $entitlement->source }}
                                @if ($entitlement->grantedBy)
                                    <span class="text-xs text-gray-400">by {{ $entitlement->grantedBy->name }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 max-w-xs truncate text-gray-500" title="{{ $entitlement->grant_note }}">{{ $entitlement->grant_note }}</td>
                            <td class="px-5 py-3">
                                @if ($entitlement->revoked_at)
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Revoked</span>
                                @elseif ($entitlement->isActive())
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Expired</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if (! $entitlement->revoked_at)
                                    <form method="POST" action="{{ route('admin.entitlements.revoke', $entitlement) }}">
                                        @csrf
                                        <x-button variant="danger" class="px-3 py-1.5">Revoke</x-button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">No entitlements yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $recent->links() }}</div>
    </section>
</x-layout>
