<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Serialises every write that takes time away from an operator's agenda.
 *
 * Booking, rescheduling and blocking time all compete for the same slots. Each
 * of them must run inside this helper: it opens a transaction and locks the
 * operator row, so a second request waits instead of reading a slot as free
 * while the first one is still writing it.
 *
 * The lock is taken on the operator row rather than on the bookings, because
 * the conflict is about a slot that does not exist yet: with no row to collide
 * on, a plain transaction would let both writers through.
 */
class AgendaLock
{
    /**
     * Run the callback while holding the write lock on an operator's agenda.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function forOperator(User|int $operator, Closure $callback): mixed
    {
        $operatorId = $operator instanceof User ? $operator->getKey() : $operator;

        return DB::transaction(function () use ($operatorId, $callback) {
            User::query()->whereKey($operatorId)->lockForUpdate()->first();

            return $callback();
        });
    }
}
