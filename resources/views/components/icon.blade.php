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
@endswitch
