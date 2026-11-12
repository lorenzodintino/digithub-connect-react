<?php

namespace App\Actions\Staff;

use App\Models\User;
use App\Support\Activity;
use Illuminate\Validation\ValidationException;

class DeleteOperator
{
    /**
     * Remove an operator with their login, availability and assignments.
     *
     * Deleting cascades to their bookings, so it is refused while any of them
     * is still to come: those have to be cancelled or reassigned first.
     */
    public function handle(User $member): void
    {
        $upcoming = $member->upcomingBookingsCount();

        if ($upcoming > 0) {
            throw ValidationException::withMessages([
                'member' => trans_choice('This operator still has one upcoming booking. Cancel or reassign it first.|This operator still has :count upcoming bookings. Cancel or reassign them first.', $upcoming),
            ]);
        }

        Activity::log('staff.deleted', $member, ['role' => $member->role->value]);

        $member->delete();
    }
}
