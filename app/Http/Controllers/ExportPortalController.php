<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\ExportPortal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPortalController extends Controller
{
    /**
     * Hand the manager a ZIP of everything their portal holds.
     *
     * A plain download, never an Inertia visit: a busy portal's export is no
     * small file, and the browser saves it as it arrives.
     */
    public function __invoke(Request $request, ExportPortal $export): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        abort_if($tenant === null, 404);
        Gate::authorize('export', $tenant);

        $path = $export->handle($tenant, $user);

        return response()
            ->download($path, $tenant->slug.'-export-'.now()->format('Y-m-d').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
