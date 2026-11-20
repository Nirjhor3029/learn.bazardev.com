<?php

namespace App\Services;

use App\Models\CoursePricing;
use App\Models\Entitlement;
use App\Models\User;

/**
 * The one place every "can this user access X" decision goes through.
 * See plans/paid-access-and-entitlements.md for the full design.
 */
class Entitlements
{
    /** Free courses are accessible to anyone, including a guest - checked before any user is required. */
    public function canAccessCourse(?User $user, string $courseSlug): bool
    {
        if (CoursePricing::isFree($courseSlug)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($this->hasActiveSubscription($user)) {
            return true;
        }

        return $user->entitlements()
            ->active()
            ->where('product_type', Entitlement::COURSE)
            ->where('product_key', $courseSlug)
            ->exists();
    }

    /**
     * Deliberately does NOT fall back to course ownership — the terminal is a
     * separate add-on. Owning the course grants nothing here on its own.
     */
    public function canAccessTerminal(User $user, string $courseSlug): bool
    {
        return $user->entitlements()
            ->active()
            ->where('product_type', Entitlement::TERMINAL)
            ->where('product_key', $courseSlug)
            ->exists();
    }

    public function hasActiveSubscription(User $user): bool
    {
        return $user->entitlements()
            ->active()
            ->where('product_type', Entitlement::SUBSCRIPTION_ALL_ACCESS)
            ->exists();
    }

    /** All of a user's currently-active entitlements, for an account/billing page. */
    public function activeFor(User $user)
    {
        return $user->entitlements()->active()->latest('starts_at')->get();
    }
}
