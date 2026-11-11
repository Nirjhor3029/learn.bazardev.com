<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Mailing;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationController extends Controller
{
    /** The signed link from the verification email lands here. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('account.edit')->with('status', 'Email verified. Thank you!');
    }

    /** "Send the link again" button on the account page. */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'Your email is already verified.');
        }

        if (! Mailing::isLive()) {
            return back()->with('status', 'Email is not switched on yet — an admin will verify your account.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['verification' => 'We could not send the email right now. Please try again later.']);
        }

        return back()->with('status', "Verification link sent to {$user->email}.");
    }
}
