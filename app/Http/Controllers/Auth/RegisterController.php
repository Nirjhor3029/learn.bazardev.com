<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Mailing;
use App\Support\ReturnTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        ReturnTo::remember($request);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: real people never see this field, bots fill everything in.
        // Pretend it worked so the bot learns nothing.
        if ($request->filled('nickname_confirm')) {
            return redirect()->route('home');
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data);

        $mailSent = false;
        if (Mailing::isLive()) {
            try {
                $user->sendEmailVerificationNotification();
                $mailSent = true;
            } catch (Throwable $e) {
                // A mail outage must never block someone from signing up.
                report($e);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', $mailSent
            ? "Welcome, {$user->name}! We sent a verification link to {$user->email}."
            : "Welcome, {$user->name}! Your account is ready — an admin will verify it shortly.");
    }
}
