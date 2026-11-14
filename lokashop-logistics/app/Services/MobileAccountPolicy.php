<?php

namespace App\Services;

/** One eligibility rule for native logistics login and authenticated requests. */
class MobileAccountPolicy
{
    public static function allowed($user): bool
    {
        return $user
            && in_array($user->role, ['rider', 'sorting_center'], true)
            && $user->approval_status === 'approved'
            && (bool) $user->active
            && $user->account_status === 'active'
            && (!(bool) ($user->verification_required ?? false) || !empty($user->email_verified_at));
    }
}
