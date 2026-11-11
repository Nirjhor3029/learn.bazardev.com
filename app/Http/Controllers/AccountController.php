<?php

namespace App\Http\Controllers;

use App\Support\Mailing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
            'mailLive' => Mailing::isLive(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $request->user()->update([
            'name' => $data['name'],
            'show_email' => $request->boolean('show_email'),
        ]);

        return back()->with('status', 'Profile saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('delete', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->is_admin) {
            return back()->withErrors(
                ['password' => 'An admin account cannot be deleted from here.'],
                'delete'
            );
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Your account and all its data have been deleted.');
    }
}
