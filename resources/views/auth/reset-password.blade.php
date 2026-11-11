<x-auth-shell title="Choose a new password" subtitle="Almost done — pick a new password for your account.">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input name="email" label="Email" type="email" :value="$email" autocomplete="email" required />

        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" required autofocus
                      hint="At least 8 characters." />

        <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />

        <x-button class="w-full">Save new password</x-button>
    </form>
</x-auth-shell>
