<x-layout title="Payment">
    <section class="mx-auto max-w-lg px-4 pt-16 pb-20 text-center">
        @if ($redirectStatus === 'success')
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <x-icon name="check" class="h-7 w-7" />
            </div>
            <h1 class="mt-4 text-xl font-bold text-gray-900">Payment received — confirming now</h1>
            <p class="mt-2 text-sm text-gray-500">
                pay.bazardev is confirming your bKash/Nagad payment. This can take a few minutes if it needs manual review.
                Your access unlocks automatically the moment it's confirmed — you don't need to do anything else.
            </p>
        @elseif ($redirectStatus === 'cancel')
            <h1 class="mt-4 text-xl font-bold text-gray-900">Payment cancelled</h1>
            <p class="mt-2 text-sm text-gray-500">No charge was made. You can try again from the course page any time.</p>
        @else
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                <x-icon name="x" class="h-7 w-7" />
            </div>
            <h1 class="mt-4 text-xl font-bold text-gray-900">Payment didn't go through</h1>
            <p class="mt-2 text-sm text-gray-500">No charge was made. You can try again from the course page any time.</p>
        @endif

        @if ($transaction)
            <p class="mt-6 text-xs text-gray-400">Reference: {{ $transaction->tran_id }}</p>
        @endif

        <a href="{{ route('home') }}" class="mt-8 inline-block text-sm font-medium text-blue-600 hover:text-blue-700">Back to learn.bazardev.com</a>
    </section>
</x-layout>
