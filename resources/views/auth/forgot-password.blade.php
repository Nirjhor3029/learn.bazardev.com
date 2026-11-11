<x-auth-shell title="Forgot your password?" subtitle="Enter your email and we'll send you a link to choose a new one.">
    @unless ($mailLive)
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Reset emails are not switched on yet. Until they are, please ask the site admin to reset your password.
        </div>
    @endunless

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" autocomplete="email" required autofocus placeholder="you@example.com" />

        <x-button class="w-full">Send reset link</x-button>
    </form>

    <x-slot:footer>
        Remembered it? <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:text-blue-700">Back to log in</a>
    </x-slot:footer>
</x-auth-shell>
