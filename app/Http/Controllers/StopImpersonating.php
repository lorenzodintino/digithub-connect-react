<?php

namespace App\Http\Controllers;

use App\Services\Impersonation;
use Illuminate\Http\RedirectResponse;

class StopImpersonating extends Controller
{
    /**
     * Hand the session back to the administrator.
     *
     * A plain route rather than a component action: the banner has to work
     * from every screen of the portal, including the ones the administrator
     * has no business being on.
     */
    public function __invoke(Impersonation $impersonation): RedirectResponse
    {
        $wasImpersonating = $impersonation->isActive();

        $impersonation->stop();

        return redirect()->route($wasImpersonating ? 'admin.portals' : 'dashboard');
    }
}
