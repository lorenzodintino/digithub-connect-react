<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Palette;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaletteRequest;
use App\Models\User;
use App\Support\Activity;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Light or dark is each person's own choice and lives in the browser. The
     * colour palette belongs to the business: its manager picks it for everyone
     * (staff and customers included). The platform administrator has no
     * business, so picks one for themselves.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        $choice = match (true) {
            $tenant !== null => [
                'scope' => 'business',
                'current' => $tenant->palette->value,
                'label' => $tenant->palette->label(),
                'editable' => $user->can('update', $tenant),
            ],
            $user->isAdmin() => [
                'scope' => 'personal',
                'current' => ($user->palette ?? Palette::Basic)->value,
                'label' => ($user->palette ?? Palette::Basic)->label(),
                'editable' => true,
            ],
            default => null,
        };

        // Not named `palette`: that is the shared prop that dresses the page.
        return Inertia::render('settings/appearance', [
            'paletteChoice' => $choice === null ? null : [...$choice, 'options' => Palette::options()],
        ]);
    }

    public function update(PaletteRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;
        $palette = $request->validated('palette');

        if ($tenant !== null) {
            Gate::authorize('update', $tenant);

            $tenant->update(['palette' => $palette]);

            Activity::log('portal.updated', $tenant, ['fields' => ['palette']]);
        } else {
            abort_unless($user->isAdmin(), 404);

            $user->update(['palette' => $palette]);
        }

        Toast::success(__('Palette saved.'));

        return back();
    }
}
