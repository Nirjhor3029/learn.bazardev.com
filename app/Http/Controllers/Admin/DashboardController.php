<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\CoursePricing;
use App\Models\Entitlement;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\View\View;

/** The /admin landing page — a quick read on the whole site, with links out to each screen. */
class DashboardController extends Controller
{
    public function index(): View
    {
        $paidTransactions = PaymentTransaction::where('status', PaymentTransaction::PAID);

        $pricedCourses = CoursePricing::where('is_free', false)->count();

        return view('admin.dashboard', [
            'stats' => [
                'users_total' => User::count(),
                'users_pending_verification' => User::whereNull('email_verified_at')->count(),
                'revenue_bdt' => (int) $paidTransactions->sum('amount'),
                'payments_paid' => (clone $paidTransactions)->count(),
                'payments_needing_attention' => PaymentTransaction::whereIn('status', [
                    PaymentTransaction::PENDING,
                    PaymentTransaction::MANUAL_REVIEW,
                ])->count(),
                'contributions_pending' => Contribution::where('status', Contribution::PENDING)->count(),
                'courses_total' => count(config('course.courses')),
                'courses_paid' => $pricedCourses,
                'entitlements_active' => Entitlement::active()->count(),
            ],
            'recentPayments' => PaymentTransaction::with('user')->latest()->limit(6)->get(),
        ]);
    }
}
