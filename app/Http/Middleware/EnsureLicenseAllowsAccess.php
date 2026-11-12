<?php

namespace App\Http\Middleware;

use App\Services\Impersonation;
use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseAllowsAccess
{
    /**
     * Routes that stay open to a portal whose licence is refused: the blocked
     * screen and the re-check, leaving, the account's own settings, and the
     * export of the portal's data (the manager's own policy still applies).
     * The public pages of portals (`portal.*`) and the home are not the
     * back-office: they answer for themselves (a refused portal is a 404
     * there, as a suspended one is), also to somebody who is signed in.
     *
     * @var array<int, string>
     */
    private const OPEN = [
        'license.*', 'logout', 'impersonation.stop', 'app.portal.export', 'portal.*', 'home',
        'profile.*', 'appearance.edit', 'security.edit', 'user-password.update',
        'two-factor.*', 'password.*', 'passkey*', 'verification.*',
    ];

    public function __construct(private readonly LicenseService $license, private readonly Impersonation $impersonation) {}

    /**
     * Send managers and operators of a portal whose licence is refused to the blocked screen.
     *
     * Nobody is logged out: the screen says why and what to do. The platform
     * administrator is never stopped, and neither is the administrator who is
     * standing in for a manager (the layout says so on every screen); a real
     * manager gets no such pass.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        if ($user === null || $tenant === null || $user->isAdmin()) {
            return $next($request);
        }

        if (! $this->license->blocksBackOffice($tenant) || $this->impersonation->isActive() || $request->routeIs(self::OPEN)) {
            return $next($request);
        }

        return redirect()->route('license.blocked');
    }
}
