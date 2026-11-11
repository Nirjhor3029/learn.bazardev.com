<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'pending' ? 'pending' : 'all';
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->when($filter === 'pending', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'filter' => $filter,
            'search' => $search,
            'totalCount' => User::count(),
            'pendingCount' => User::whereNull('email_verified_at')->count(),
        ]);
    }

    /** Manual approval: marks the account verified without needing the email link. */
    public function verify(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return back()->with('status', "{$user->name} is now verified.");
    }
}
