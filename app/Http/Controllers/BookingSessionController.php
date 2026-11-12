<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ServiceSession;
use App\Models\User;
use App\Services\AgendaLock;
use App\Services\SlotFinder;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Who gives a group session, changed from the details of one of its bookings.
 */
class BookingSessionController extends Controller
{
    /**
     * Put another member of staff on the session, if they are free then.
     */
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $session = $this->session($booking);

        $userId = $request->validate(['user' => ['required', 'integer']])['user'];
        $operator = User::query()->whereKey($userId)->firstOrFail();

        abort_unless($operator->tenant_id === $session->tenant_id, 403);

        AgendaLock::forOperator($operator, function () use ($session, $operator): void {
            $session->refresh()->load('service.tenant');
            abort_unless((new SlotFinder($session->service, $operator))->joining($session)->canBookAt($session->starts_at), 422);
            $session->operators()->syncWithoutDetaching([$operator->getKey()]);
        });

        Toast::success(__(':name is now on this session.', ['name' => $operator->name]));

        return back();
    }

    /**
     * Take somebody off the session; somebody has to stay.
     */
    public function destroy(Booking $booking, User $user): RedirectResponse
    {
        $session = $this->session($booking);

        if ($session->operators()->count() <= 1) {
            throw ValidationException::withMessages(['session' => __('Somebody has to give this session.')]);
        }

        $session->operators()->detach($user->getKey());

        // The bookings point at one of them for the emails and the agenda: if
        // that is the one leaving, hand them to whoever is left.
        $remaining = $session->operators()->orderBy('users.id')->first();

        $session->bookings()->where('user_id', $user->getKey())->update(['user_id' => $remaining->getKey()]);

        Toast::success(__('Removed from this session.'));

        return back();
    }

    /**
     * The session of the booking, once the reader may change it.
     */
    private function session(Booking $booking): ServiceSession
    {
        Gate::authorize('update', $booking->tenant);

        abort_if($booking->session === null, 404);

        return $booking->session;
    }
}
