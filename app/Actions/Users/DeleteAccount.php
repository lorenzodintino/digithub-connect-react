<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Activity;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    /**
     * Remove any account of the platform, from the administrator's list.
     *
     * Deleting cascades to the bookings it holds, so it is refused while any
     * of them is still to come: those have to be cancelled or reassigned first.
     */
    public function handle(User $account): void
    {
        $upcoming = $account->upcomingBookingsCount();

        if ($upcoming > 0) {
            throw ValidationException::withMessages([
                'account' => trans_choice('This account still has one upcoming booking. Cancel or reassign it first.|This account still has :count upcoming bookings. Cancel or reassign them first.', $upcoming),
            ]);
        }

        Activity::log('account.deleted', $account, ['role' => $account->role->value], $account->tenant_id);

        $account->delete();
    }
}
