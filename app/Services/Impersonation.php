<?php

namespace App\Services;

use App\Models\User;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Lets the platform administrator work inside a portal as its manager.
 *
 * It exists so support can see exactly what a customer sees without anybody
 * swapping passwords. Three things make it acceptable rather than a back door:
 * it expires on its own, it is impossible to miss while it is on, and every
 * line it writes in the register says who was really behind it.
 */
class Impersonation
{
    /**
     * How long a session lasts before it drops by itself.
     */
    public const MINUTES = 30;

    /**
     * Session key holding the administrator who stepped in.
     */
    private const ACTOR = 'impersonation.actor';

    /**
     * Session key holding the instant it stops being valid.
     */
    private const EXPIRES = 'impersonation.expires_at';

    /**
     * Take the place of a portal manager.
     *
     * @throws AuthorizationException|HttpException
     */
    public function start(User $admin, User $target): void
    {
        abort_unless($admin->isAdmin(), 403);

        // Never onto another administrator, and never one level deeper: both
        // would make the register impossible to read back.
        abort_if($target->isAdmin(), 403);
        abort_if($this->isActive(), 409);
        abort_unless($target->is_active && $target->belongsToTenant(), 403);

        Session::put(self::ACTOR, $admin->getKey());
        Session::put(self::EXPIRES, CarbonImmutable::now()->addMinutes(self::MINUTES)->toIso8601String());

        Activity::log('impersonation.started', $target, [
            'minutes' => self::MINUTES,
        ], $target->tenant_id, $target->name, $admin);

        Auth::login($target);
    }

    /**
     * Go back to being the administrator.
     */
    public function stop(): void
    {
        $admin = $this->actor();

        if ($admin === null) {
            return;
        }

        $target = Auth::user();

        Session::forget([self::ACTOR, self::EXPIRES]);

        Auth::login($admin);

        Activity::log(
            'impersonation.stopped',
            $target instanceof User ? $target : null,
            [],
            $target instanceof User ? $target->tenant_id : null,
            $target instanceof User ? $target->name : null,
            $admin,
        );
    }

    /**
     * Determine whether somebody is being impersonated right now.
     */
    public function isActive(): bool
    {
        return $this->actor() !== null;
    }

    /**
     * The administrator behind the current session, when there is one.
     */
    public function actor(): ?User
    {
        $id = Session::get(self::ACTOR);

        if ($id === null || $this->hasExpired()) {
            return null;
        }

        $admin = User::query()->find($id);

        return $admin instanceof User && $admin->isAdmin() ? $admin : null;
    }

    /**
     * When the current session gives out.
     */
    public function expiresAt(): ?CarbonImmutable
    {
        $expires = Session::get(self::EXPIRES);

        return is_string($expires) ? CarbonImmutable::parse($expires) : null;
    }

    /**
     * Determine whether the session is past its time.
     */
    public function hasExpired(): bool
    {
        $expires = $this->expiresAt();

        return $expires !== null && $expires->isPast();
    }

    /**
     * Drop an expired session, putting the administrator back in their place.
     */
    public function stopIfExpired(): void
    {
        if (Session::has(self::ACTOR) && $this->hasExpired()) {
            $admin = User::query()->find(Session::get(self::ACTOR));

            Session::forget([self::ACTOR, self::EXPIRES]);

            if ($admin instanceof User) {
                Auth::login($admin);

                Activity::log('impersonation.expired', null, [], null, null, $admin);
            }
        }
    }
}
