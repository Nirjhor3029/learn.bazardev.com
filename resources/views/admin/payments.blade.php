<x-layout title="Admin · Payments">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <x-admin.nav />
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Payments</h1>
        <p class="mt-2 text-sm text-gray-500">
            Every pay.bazardev transaction, read-only. `pending` and `manual_review` resolve on their own — pay.bazardev
            never fires a webhook for `expired` or `failed`, so <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">app:reconcile-payments</code>
            polls for those every 15 minutes.
        </p>

        <form method="GET" class="mt-6 flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or email"
                   class="w-56 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">

            <div class="flex flex-wrap items-center gap-1">
                <a href="{{ route('admin.payments.index', array_filter(['q' => $search])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ ! $status ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">All</a>
                @foreach ($statuses as $s)
                    <a href="{{ route('admin.payments.index', array_filter(['q' => $search, 'status' => $s])) }}"
                       class="rounded-lg px-3 py-1.5 text-sm font-medium capitalize transition-colors {{ $status === $s ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">{{ str_replace('_', ' ', $s) }}</a>
                @endforeach
            </div>

            <x-button type="submit" variant="secondary" class="px-3 py-1.5">Search</x-button>
        </form>

        <div class="mt-4 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-3xl text-left text-sm">
                <thead class="border-b border-gray-100 text-xs tracking-wide text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-medium">User</th>
                        <th class="px-5 py-3 font-medium">Intent</th>
                        <th class="px-5 py-3 font-medium">Product</th>
                        <th class="px-5 py-3 font-medium">Amount</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Reference</th>
                        <th class="px-5 py-3 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transactions as $tx)
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $tx->user?->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-400">{{ $tx->user?->email }}</p>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ str_replace('_', ' ', $tx->intent) }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $tx->product_key ?? '—' }}</td>
                            <td class="px-5 py-3 tabular-nums text-gray-900">{{ number_format($tx->amount) }} {{ $tx->currency }}</td>
                            <td class="px-5 py-3"><x-admin.payment-status-badge :status="$tx->status" /></td>
                            <td class="px-5 py-3 text-xs text-gray-500">
                                <span class="font-mono">{{ $tx->tran_id }}</span>
                                @if ($tx->gateway_reference)
                                    <br><span class="font-mono text-gray-400">{{ $tx->gateway_reference }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500" title="{{ $tx->created_at }}">{{ $tx->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-gray-400">No matching payments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $transactions->links() }}</div>
    </section>
</x-layout>
