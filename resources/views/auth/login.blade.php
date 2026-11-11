<x-auth-shell title="Welcome back" subtitle="Log in to pick up where you left off.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" autocomplete="email" required autofocus placeholder="you@example.com" />

        <x-form.input name="password" label="Password" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-gray-600">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-200">
                Keep me logged in
            </label>
            <a href="{{ route('password.request') }}" class="font-medium text-blue-600 hover:text-blue-700">Forgot password?</a>
        </div>

        <x-button class="w-full">Log in</x-button>
    </form>

    <x-slot:footer>
        New here? <a href="{{ route('register') }}" class="font-semibold text-blue-600 hover:text-blue-700">Create a free account</a>
    </x-slot:footer>
</x-auth-shell>
