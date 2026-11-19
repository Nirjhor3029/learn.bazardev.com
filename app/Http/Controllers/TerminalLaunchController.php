<?php

namespace App\Http\Controllers;

use App\Models\TerminalLaunchTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// Hands a logged-in reader off to terminal.bazardev.com without sharing sessions
// or cookies between the two apps. See terminal.bazardev.com/PROJECT_CONTEXT.md §6.
class TerminalLaunchController extends Controller
{
    // Auth-protected: a logged-in reader clicks "Open Terminal" here, we mint a
    // one-time, 60-second ticket, and bounce them to the terminal app with it.
    public function launch(Request $request)
    {
        $ticket = TerminalLaunchTicket::create([
            'user_id' => Auth::id(),
            'token' => Str::random(64),
            'expires_at' => now()->addSeconds(60),
        ]);

        return redirect()->away(
            config('services.terminal.url').'/launch?ticket='.$ticket->token
        );
    }

    // Internal API, protected by a shared secret (see the 'terminal.shared_secret'
    // config / TERMINAL_SHARED_SECRET env) — NOT by the normal web session, because
    // the caller is the terminal orchestrator server-to-server, not a browser.
    public function verify(Request $request)
    {
        $request->validate(['ticket' => ['required', 'string']]);

        $ticket = TerminalLaunchTicket::where('token', $request->input('ticket'))->first();

        if (! $ticket || ! $ticket->isValid()) {
            return response()->json(['error' => 'invalid_or_expired_ticket'], Response::HTTP_GONE);
        }

        // Direct property + save, not mass-update: `used_at` is deliberately not
        // fillable (it's internal bookkeeping, never something a caller should set).
        $ticket->used_at = now();
        $ticket->save();

        $user = $ticket->user;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
