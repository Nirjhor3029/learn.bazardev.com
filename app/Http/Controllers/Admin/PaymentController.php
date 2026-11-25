<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only visibility into money that moved (or tried to) through pay.bazardev. */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $transactions = PaymentTransaction::query()
            ->with('user')
            ->when($status && in_array($status, $this->statuses(), true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments', [
            'transactions' => $transactions,
            'status' => $status,
            'search' => $search,
            'statuses' => $this->statuses(),
        ]);
    }

    private function statuses(): array
    {
        return [
            PaymentTransaction::PENDING,
            PaymentTransaction::PAID,
            PaymentTransaction::FAILED,
            PaymentTransaction::EXPIRED,
            PaymentTransaction::MANUAL_REVIEW,
            PaymentTransaction::REFUNDED,
        ];
    }
}
