<x-layout title="Admin · Course pricing">
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-20">
        <x-admin.nav />
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Course pricing</h1>
        <p class="mt-2 text-sm text-gray-500">
            A course with no row here yet stays open/free, exactly as it is today — the paywall only switches on
            for a course once you uncheck “Free” and save, deliberately, one course at a time. Price is in whole
            BDT. Payment collection itself is not live yet — this screen controls access only.
        </p>

        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-3xl text-left text-sm">
                <thead class="border-b border-gray-100 text-xs tracking-wide text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-medium">Course</th>
                        <th class="px-5 py-3 font-medium">Free?</th>
                        <th class="px-5 py-3 font-medium">Price (BDT)</th>
                        <th class="px-5 py-3 font-medium">In subscription?</th>
                        <th class="px-5 py-3 font-medium">Last updated</th>
                        <th class="px-5 py-3 text-right font-medium">Save</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($courses as $course)
                        @php $pricing = $course['pricing']; $formId = 'pricing-'.$course['slug']; @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $course['title'] }}</p>
                                <p class="text-xs text-gray-400">{{ $course['slug'] }} · {{ $course['section'] }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <input form="{{ $formId }}" type="checkbox" name="is_free" value="1" @checked($pricing ? $pricing->is_free : true)
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-5 py-3">
                                <input form="{{ $formId }}" type="number" name="price_amount" min="0" max="1000000" step="1"
                                       value="{{ $pricing?->price_amount ?? 0 }}"
                                       class="w-28 rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                            </td>
                            <td class="px-5 py-3">
                                <input form="{{ $formId }}" type="checkbox" name="subscription_eligible" value="1" @checked($pricing?->subscription_eligible ?? true)
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500">
                                @if ($pricing)
                                    {{ $pricing->updated_at->diffForHumans() }}
                                    @if ($pricing->updatedBy)
                                        by {{ $pricing->updatedBy->name }}
                                    @endif
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-500">Not configured — currently open/free</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <form id="{{ $formId }}" method="POST" action="{{ route('admin.pricing.update', $course['slug']) }}">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <x-button form="{{ $formId }}" variant="secondary" class="px-3 py-1.5">Save</x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
