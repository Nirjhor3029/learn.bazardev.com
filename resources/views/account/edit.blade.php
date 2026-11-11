<x-layout title="Account settings">
    <section class="mx-auto max-w-2xl px-4 pt-10 pb-20">
        {{-- Who you are --}}
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-lg font-semibold text-white">{{ $user->initials() }}</span>
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-extrabold tracking-tight">{{ $user->name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <span class="truncate">{{ $user->email }}</span>
                    @if ($user->hasVerifiedEmail())
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Verified</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Not verified yet</span>
                    @endif
                    @if ($user->is_admin)
                        <span class="rounded-full bg-gray-900 px-2 py-0.5 text-xs font-medium text-white">Admin</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Verification nudge --}}
        @unless ($user->hasVerifiedEmail())
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                @if ($mailLive)
                    <p class="font-semibold">Please verify your email</p>
                    <p class="mt-1 text-amber-800">We sent a link to {{ $user->email }}. Click it to verify your account. Reading and your personal features work in the meantime.</p>
                    <form method="POST" action="{{ route('verification.send') }}" class="mt-3">
                        @csrf
                        <x-button variant="secondary" class="px-3 py-1.5">Send the link again</x-button>
                    </form>
                    @error('verification')
                        <p class="mt-2 text-rose-600">{{ $message }}</p>
                    @enderror
                @else
                    <p class="font-semibold">Waiting for approval</p>
                    <p class="mt-1 text-amber-800">An admin will verify your account soon. You don't need to do anything — and you can keep using the site in the meantime.</p>
                @endif
            </div>
        @endunless

        {{-- Profile --}}
        <div class="mt-8 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold tracking-tight">Profile</h2>
            <p class="mt-1 text-sm text-gray-500">Your name is shown next to anything you contribute.</p>

            <form method="POST" action="{{ route('account.update') }}" class="mt-6 space-y-5" novalidate>
                @csrf
                @method('PATCH')

                <x-form.input name="name" label="Your name" :value="$user->name" autocomplete="name" required />

                <label class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <input type="checkbox" name="show_email" value="1" @checked(old('show_email', $user->show_email))
                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-200">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Show my email next to my contributions</span>
                        <span class="mt-0.5 block text-xs text-gray-500">Off by default. Anyone on the internet, including spam bots, can read a public email — only turn this on if you want people to contact you.</span>
                    </span>
                </label>

                <x-button>Save profile</x-button>
            </form>
        </div>

        {{-- Password --}}
        <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold tracking-tight">Change password</h2>

            <form method="POST" action="{{ route('account.password') }}" class="mt-6 space-y-5" novalidate>
                @csrf
                @method('PUT')

                <x-form.input name="current_password" label="Current password" type="password" bag="password" autocomplete="current-password" required />
                <x-form.input name="password" label="New password" type="password" bag="password" id="new_password" autocomplete="new-password" required hint="At least 8 characters." />
                <x-form.input name="password_confirmation" label="Confirm new password" type="password" bag="password" autocomplete="new-password" required />

                <x-button>Change password</x-button>
            </form>
        </div>

        {{-- Danger zone (admins cannot delete themselves here) --}}
        @unless ($user->is_admin)
            <details class="mt-6 rounded-2xl border border-rose-100 bg-white p-6 shadow-sm sm:p-8" @if ($errors->delete->any()) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-rose-600">Delete my account</summary>

                <p class="mt-4 text-sm text-gray-600">This permanently deletes your account and everything saved in it — progress, bookmarks, notes and anything you contributed to a chapter. This cannot be undone.</p>

                <form method="POST" action="{{ route('account.destroy') }}" class="mt-5 space-y-5" novalidate>
                    @csrf
                    @method('DELETE')

                    <x-form.input name="password" label="Type your password to confirm" type="password" bag="delete" id="delete_password" autocomplete="current-password" required />

                    <x-button variant="danger">Delete my account forever</x-button>
                </form>
            </details>
        @endunless
    </section>
</x-layout>
