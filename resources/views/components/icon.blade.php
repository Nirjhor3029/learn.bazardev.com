{{-- Small inline icons. Size and colour come from the class you pass: <x-icon name="check" class="h-4 w-4" /> --}}
@props(['name'])

@switch($name)
    @case('check')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
        @break
    @case('bookmark')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V21l-6-3.75L6 21V4.5Z"/></svg>
        @break
    @case('bookmark-solid')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V21l-6-3.75L6 21V4.5Z"/></svg>
        @break
    @case('play')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.3 3.2A1 1 0 0 0 4.75 4v12a1 1 0 0 0 1.55.83l9-6a1 1 0 0 0 0-1.66l-9-6Z"/></svg>
        @break
    @case('pencil')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M14.6 2.9a2 2 0 0 1 2.8 2.8l-.9.9-2.8-2.8.9-.9ZM12.6 4.9l2.8 2.8-8.1 8.1a2 2 0 0 1-.9.5l-3 .8a.5.5 0 0 1-.6-.6l.8-3a2 2 0 0 1 .5-.9l8.5-7.7Z"/></svg>
        @break
    @case('lock')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1.5a4 4 0 0 0-4 4V8H5.5A1.5 1.5 0 0 0 4 9.5v7A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-7A1.5 1.5 0 0 0 14.500 8H14V5.5a4 4 0 0 0-4-4Zm2.5 6.500v-2.500a2.5 2.5 0 0 0-5 0V8h5Z" clip-rule="evenodd"/></svg>
        @break
    @case('arrow-right')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.7-3.71a.75.75 0 0 1 1.06-1.06l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06l3.7-3.73H3.750A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        @break
    @case('link')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 11.500a3 3 0 0 0 4.240 0l3-3a3 3 0 0 0-4.240-4.240l-1 1"/><path d="M11.500 8.500a3 3 0 0 0-4.240 0l-3 3a3 3 0 0 0 4.240 4.240l1-1"/></svg>
        @break
    @case('bulb')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 14.500h5M8.250 17h3.500M10 2.750a5 5 0 0 0-3 9c.5.400.750 1 .750 1.500v1.250h4.500v-1.250c0-.500.250-1.100.750-1.500a5 5 0 0 0-3-9Z"/></svg>
        @break
    @case('flag')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.500 17.500v-14M4.500 4.250h9.750l-2 3.250 2 3.250H4.500"/></svg>
        @break
    @case('x')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>
        @break
    @case('grid')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4.5A1.5 1.5 0 0 1 4.5 3h3A1.5 1.5 0 0 1 9 4.5v3A1.5 1.5 0 0 1 7.5 9h-3A1.5 1.5 0 0 1 3 7.5v-3Zm8 0A1.5 1.5 0 0 1 12.5 3h3A1.5 1.5 0 0 1 17 4.5v3A1.5 1.5 0 0 1 15.5 9h-3A1.5 1.5 0 0 1 11 7.5v-3ZM3 12.5A1.5 1.5 0 0 1 4.5 11h3A1.5 1.5 0 0 1 9 12.5v3A1.5 1.5 0 0 1 7.5 17h-3A1.5 1.5 0 0 1 3 15.5v-3Zm8 0a1.5 1.5 0 0 1 1.5-1.5h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a1.5 1.5 0 0 1-1.5-1.5v-3Z"/></svg>
        @break
    @case('users')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6.5-1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.5 16a5.5 5.5 0 0 1 11 0v.25a.75.75 0 0 1-.75.75h-9.5a.75.75 0 0 1-.75-.75V16Zm12 .75h4.25a.75.75 0 0 0 .75-.75v-.1a4.5 4.5 0 0 0-6.4-4.07c1.46.98 2.4 2.64 2.4 4.42v.5Z"/></svg>
        @break
    @case('currency')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.25"/><path d="M12.3 7.6a2 2 0 0 0-1.9-1.1h-.8a1.85 1.85 0 0 0 0 3.7h.8a1.85 1.85 0 0 1 0 3.7h-.8a2 2 0 0 1-1.9-1.1M10 5.3v1.2M10 13.5v1.2"/></svg>
        @break
    @case('clock')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.25"/><path d="M10 6v4l3 2"/></svg>
        @break
    @case('book')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 4.5c2-1 4.5-1 6.5.3 2-1.3 4.5-1.3 6.5-.3v10.7c-2-1-4.5-1-6.5.3-2-1.3-4.5-1.3-6.5-.3V4.5Z"/><path d="M10 4.8v10.7"/></svg>
        @break
    @case('chevron-down')
        <svg {{ $attributes }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.2 7.2a1 1 0 0 1 1.4 0L10 10.6l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.6a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
        @break
@endswitch
