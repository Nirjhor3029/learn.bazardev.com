{{-- Switch between the admin screens. Shows how many contributions are waiting. --}}
@php
    $waiting = \App\Models\Contribution::where('status', \App\Models\Contribution::PENDING)->count();
    $tabs = [
        ['route' => 'admin.contributions.index', 'label' => 'Contributions', 'badge' => $waiting],
        ['route' => 'admin.users.index', 'label' => 'Users', 'badge' => 0],
    ];
@endphp

<nav class="flex items-center gap-1 text-sm" aria-label="Admin">
    <span class="mr-2 font-semibold text-blue-600">Admin</span>
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}" @if ($active) aria-current="page" @endif
           class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-colors {{ $active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
            {{ $tab['label'] }}
            @if ($tab['badge'] > 0)
                <span class="rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 tabular-nums">{{ $tab['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
