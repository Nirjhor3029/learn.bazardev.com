{{-- Switch between the admin screens. Shows how many items are waiting on each. --}}
@php
    $waitingContributions = \App\Models\Contribution::where('status', \App\Models\Contribution::PENDING)->count();
    $waitingPayments = \App\Models\PaymentTransaction::whereIn('status', [
        \App\Models\PaymentTransaction::PENDING,
        \App\Models\PaymentTransaction::MANUAL_REVIEW,
    ])->count();
    $pendingUsers = \App\Models\User::whereNull('email_verified_at')->count();

    $tabs = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'badge' => 0],
        ['route' => 'admin.payments.index', 'label' => 'Payments', 'icon' => 'currency', 'badge' => $waitingPayments],
        ['route' => 'admin.contributions.index', 'label' => 'Contributions', 'icon' => 'bulb', 'badge' => $waitingContributions],
        ['route' => 'admin.users.index', 'label' => 'Users', 'icon' => 'users', 'badge' => $pendingUsers],
        ['route' => 'admin.pricing.index', 'label' => 'Pricing', 'icon' => 'book', 'badge' => 0],
        ['route' => 'admin.entitlements.index', 'label' => 'Access grants', 'icon' => 'key', 'badge' => 0],
    ];
@endphp

<div class="mb-2 flex items-center gap-2">
    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-gray-900 text-xs font-bold text-white">A</span>
    <span class="text-sm font-semibold text-gray-900">Admin</span>
</div>

<nav class="-mx-1 flex items-center gap-1 overflow-x-auto pb-1 text-sm" aria-label="Admin">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}" @if ($active) aria-current="page" @endif
           class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-colors {{ $active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
            <x-icon :name="$tab['icon']" class="h-4 w-4 {{ $active ? 'text-white' : 'text-gray-400' }}" />
            {{ $tab['label'] }}
            @if ($tab['badge'] > 0)
                <span class="rounded-full px-1.5 text-xs font-semibold tabular-nums {{ $active ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $tab['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
