<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\User;
use App\Tenancy\CurrentTenant;

/**
 * Found automatically by name (App\Models\Booking -> App\Policies\BookingPolicy).
 */
class BookingPolicy
{
    /**
     * Cancel a lesson that hasn't started yet: its student, its teacher, or the workspace owner.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Confirmed || ! $booking->starts_at->isFuture()) {
            return false;
        }

        if ($user->id === $booking->student_id || $user->id === $booking->teacher_id) {
            return true;
        }

        return $this->roleIn($user, $booking) === Role::Owner;
    }

    /**
     * The user's role in the booking's workspace. On tenant pages the middleware has already
     * loaded the signed-in user's role, so an owner's list of many lessons doesn't run one query
     * per lesson. For anyone else, look it up.
     */
    private function roleIn(User $user, Booking $booking): ?Role
    {
        $current = CurrentTenant::resolve();

        if ($current !== null && $current->tenant->id === $booking->tenant_id && $user->id === auth()->id()) {
            return $current->role;
        }

        return $user->roleIn($booking->tenant);
    }
}
