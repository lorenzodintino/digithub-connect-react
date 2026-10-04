<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Serialises agenda and resource writes within a portal.
 *
 * Booking, rescheduling and blocking time all compete for the same slots. Each
 * of them must run inside this helper: it opens a transaction and locks the
 * portal row before the operator row, so a second request waits instead of reading a slot as free
 * while the first one is still writing it.
 *
 * The locks are taken on existing portal and operator rows rather than bookings, because
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

        $tenantId = $operator instanceof User ? $operator->tenant_id : User::query()->whereKey($operatorId)->firstOrFail()->tenant_id;

        return self::forTenant($tenantId, function () use ($operatorId, $callback) {
            User::query()->whereKey($operatorId)->lockForUpdate()->first();

            return $callback();
        });
    }

    /**
     * Serialise people, shared resources and their configuration in one portal.
     * Always acquire the portal before operator, session or booking row locks.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function forTenant(Tenant|int $tenant, Closure $callback): mixed
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return DB::transaction(function () use ($tenantId, $callback) {
            Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

            return $callback();
        }, attempts: 3);
    }
}
