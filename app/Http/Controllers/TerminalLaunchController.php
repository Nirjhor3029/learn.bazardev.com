<?php

namespace App\Http\Controllers;

use App\Models\TerminalLaunchTicket;
use App\Services\Entitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// Hands a logged-in reader off to terminal.bazardev.com without sharing sessions
// or cookies between the two apps. See terminal.bazardev.com/PROJECT_CONTEXT.md §6.
class TerminalLaunchController extends Controller
{
    // There is only one terminal product today. Generalizing this to other
    // courses is explicitly out of scope for the payment feature (Phase 5,
    // not committed) - see plans/paid-access-and-entitlements.md §2/§7.
    private const COURSE = 'linux-shell';

    public function __construct(private Entitlements $entitlements) {}

    // Auth-protected: a logged-in reader clicks "Open Terminal" here. The
    // terminal is a separate paid add-on (not unlocked by owning the course),
    // so access is checked BEFORE a ticket is minted - an unentitled reader
    // never gets a ticket at all, and the orchestrator needs no changes of
    // its own (it keeps trusting a valid, unused, unexpired ticket as before).
    public function launch(Request $request)
    {
        $user = $request->user();

        if (! $this->entitlements->canAccessTerminal($user, self::COURSE)) {
            return back()->with('status', 'Terminal access is a separate add-on and needs to be purchased (or granted by an admin) first.');
        }

        $ticket = TerminalLaunchTicket::create([
            'user_id' => $user->id,
            'course_slug' => self::COURSE,
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
            // Informational only, for the orchestrator's own logging - the
            // entitlement check already happened at mint time (above), so
            // the orchestrator does not need to (and should not) re-check this.
            'course' => $ticket->course_slug,
        ]);
    }
}
