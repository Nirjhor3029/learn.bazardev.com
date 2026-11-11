{{-- One labelled text field with its hint and validation error. Passwords get a Show/Hide button. --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'bag' => 'default', 'id' => null])

@php
    $id = $id ?? $name;
    $error = $errors->getBag($bag)->first($name);
    $isPassword = $type === 'password';
    $border = $error
        ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100'
        : 'border-gray-200 focus:border-blue-500 focus:ring-blue-100';
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>

    <div class="relative mt-1.5">
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
               @unless ($isPassword) value="{{ old($name, $value) }}" @endunless
               @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
               {{ $attributes->merge(['class' => "block w-full rounded-lg border bg-white px-3.5 py-2.5 text-base text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:ring-4 sm:text-sm {$border}".($isPassword ? ' pr-16' : '')]) }}>

        @if ($isPassword)
            <button type="button" data-toggle-password="{{ $id }}" aria-pressed="false"
                    class="absolute inset-y-0 right-0 my-1 mr-1 rounded-md px-3 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900">Show</button>
        @endif
    </div>

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm text-rose-600">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
