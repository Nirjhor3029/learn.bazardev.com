<x-layout title="Admin · Dashboard">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <x-admin.nav />
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Dashboard</h1>
        <p class="mt-2 text-sm text-gray-500">A quick read on the whole site — courses, payments, users, and what's waiting on you.</p>

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <a href="{{ route('admin.payments.index') }}" class="group rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><x-icon name="currency" class="h-5 w-5" /></span>
                    @if ($stats['payments_needing_attention'] > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 tabular-nums">{{ $stats['payments_needing_attention'] }} waiting</span>
                    @endif
                </div>
                <p class="mt-3 text-2xl font-bold tracking-tight text-gray-900 tabular-nums">{{ number_format($stats['revenue_bdt']) }} <span class="text-sm font-medium text-gray-400">BDT</span></p>
                <p class="mt-0.5 text-sm text-gray-500">Revenue &middot; {{ $stats['payments_paid'] }} paid {{ Str::plural('transaction', $stats['payments_paid']) }}</p>
            </a>

            <a href="{{ route('admin.users.index') }}" class="group rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="users" class="h-5 w-5" /></span>
                    @if ($stats['users_pending_verification'] > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 tabular-nums">{{ $stats['users_pending_verification'] }} unverified</span>
                    @endif
                </div>
                <p class="mt-3 text-2xl font-bold tracking-tight text-gray-900 tabular-nums">{{ number_format($stats['users_total']) }}</p>
                <p class="mt-0.5 text-sm text-gray-500">Registered users</p>
            </a>

            <a href="{{ route('admin.entitlements.index') }}" class="group rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-icon name="key" class="h-5 w-5" /></span>
                </div>
                <p class="mt-3 text-2xl font-bold tracking-tight text-gray-900 tabular-nums">{{ number_format($stats['entitlements_active']) }}</p>
                <p class="mt-0.5 text-sm text-gray-500">Active access grants</p>
            </a>

            <a href="{{ route('admin.pricing.index') }}" class="group rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600"><x-icon name="book" class="h-5 w-5" /></span>
                </div>
                <p class="mt-3 text-2xl font-bold tracking-tight text-gray-900 tabular-nums">{{ $stats['courses_paid'] }} / {{ $stats['courses_total'] }}</p>
                <p class="mt-0.5 text-sm text-gray-500">Courses priced (rest stay free)</p>
            </a>

            <a href="{{ route('admin.contributions.index') }}" class="group rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><x-icon name="bulb" class="h-5 w-5" /></span>
                </div>
                <p class="mt-3 text-2xl font-bold tracking-tight text-gray-900 tabular-nums">{{ $stats['contributions_pending'] }}</p>
                <p class="mt-0.5 text-sm text-gray-500">Contributions to review</p>
            </a>
        </div>

        <div class="mt-8 flex items-center justify-between">
            <h2 class="text-lg font-bold tracking-tight text-gray-900">Recent payments</h2>
            <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:underline">
                View all <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </div>

        <div class="mt-3 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-2xl text-left text-sm">
                <thead class="border-b border-gray-100 text-xs tracking-wide text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-medium">User</th>
                        <th class="px-5 py-3 font-medium">Product</th>
                        <th class="px-5 py-3 font-medium">Amount</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentPayments as $tx)
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $tx->user?->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-400">{{ $tx->user?->email }}</p>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $tx->product_key ?? '—' }}</td>
                            <td class="px-5 py-3 tabular-nums text-gray-900">{{ number_format($tx->amount) }} {{ $tx->currency }}</td>
                            <td class="px-5 py-3"><x-admin.payment-status-badge :status="$tx->status" /></td>
                            <td class="px-5 py-3 text-xs text-gray-500">{{ $tx->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
