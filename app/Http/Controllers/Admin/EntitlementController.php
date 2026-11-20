<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manual, admin-decided access grants — the tool for "admin manually decides
 * who gets free access" (existing users with progress, special cases, etc).
 * Every grant needs a note, for the audit trail; nothing is ever hard-deleted,
 * a revoke just sets `revoked_at`.
 */
class EntitlementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = $search !== ''
            ? User::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->limit(10)->get()
            : collect();

        $recent = Entitlement::query()
            ->with(['user', 'grantedBy'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.entitlements', [
            'search' => $search,
            'users' => $users,
            'recent' => $recent,
            'courses' => config('course.courses'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'product_type' => ['required', 'in:course,terminal,subscription_all_access'],
            'product_key' => ['required_unless:product_type,subscription_all_access', 'nullable', 'string'],
            'grant_note' => ['required', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($data['product_type'] !== 'subscription_all_access') {
            abort_unless(array_key_exists($data['product_key'], config('course.courses')), 422, 'Unknown course.');
        }

        Entitlement::create([
            'user_id' => $data['user_id'],
            'product_type' => $data['product_type'],
            'product_key' => $data['product_type'] === 'subscription_all_access' ? null : $data['product_key'],
            'source' => Entitlement::ADMIN_GRANT,
            'granted_by' => $request->user()->id,
            'grant_note' => $data['grant_note'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $user = User::find($data['user_id']);

        return back()->with('status', "Granted {$this->describe($data)} to {$user->name}.");
    }

    public function revoke(Entitlement $entitlement): RedirectResponse
    {
        if ($entitlement->revoked_at === null) {
            $entitlement->forceFill(['revoked_at' => now()])->save();
        }

        return back()->with('status', 'Entitlement revoked.');
    }

    private function describe(array $data): string
    {
        return match ($data['product_type']) {
            'subscription_all_access' => 'an all-access subscription',
            'terminal' => "terminal access for {$data['product_key']}",
            default => config("course.courses.{$data['product_key']}.title", $data['product_key']),
        };
    }
}
