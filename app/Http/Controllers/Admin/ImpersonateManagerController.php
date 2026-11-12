<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImpersonateManagerController extends Controller
{
    /**
     * Step into the portal as its manager, to see exactly what they see.
     */
    public function __invoke(Request $request, Tenant $tenant, Impersonation $impersonation): RedirectResponse
    {
        Gate::authorize('view', $tenant);

        $manager = $tenant->manager()->first();

        abort_if($manager === null, 404);

        /** @var User $admin */
        $admin = $request->user();

        $impersonation->start($admin, $manager);

        return to_route('dashboard');
    }
}
