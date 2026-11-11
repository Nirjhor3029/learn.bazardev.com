<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Services\Learning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The review queue: nothing a reader sends in becomes public until it is approved here. */
class ContributionController extends Controller
{
    public function index(Request $request, Learning $learning): View
    {
        $status = in_array($request->query('status'), [Contribution::APPROVED, Contribution::REJECTED], true)
            ? $request->query('status')
            : Contribution::PENDING;

        $contributions = Contribution::query()
            ->with(['user', 'reviewer'])
            ->where('status', $status)
            // Oldest first while waiting (first come, first served); newest first once decided.
            ->when($status === Contribution::PENDING, fn ($q) => $q->oldest(), fn ($q) => $q->latest('reviewed_at'))
            ->paginate(20)
            ->withQueryString();

        $contributions->getCollection()->each(function (Contribution $contribution) use ($learning) {
            $contribution->setAttribute('place', $learning->describe($contribution->course, $contribution->chapter));
        });

        return view('admin.contributions', [
            'contributions' => $contributions,
            'status' => $status,
            'counts' => Contribution::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, Contribution $contribution): RedirectResponse
    {
        $this->decide($request, $contribution, Contribution::APPROVED, null);

        return back()->with('status', $contribution->type === 'correction'
            ? 'Marked as resolved.'
            : "Approved — it now shows under the chapter with {$contribution->user->name}'s name.");
    }

    public function reject(Request $request, Contribution $contribution): RedirectResponse
    {
        $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);
        $note = trim((string) $request->input('admin_note'));

        $this->decide($request, $contribution, Contribution::REJECTED, $note === '' ? null : $note);

        return back()->with('status', 'Rejected — it is not shown to anyone but the sender.');
    }

    private function decide(Request $request, Contribution $contribution, string $status, ?string $note): void
    {
        $contribution->forceFill([
            'status' => $status,
            'admin_note' => $note,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();
    }
}
