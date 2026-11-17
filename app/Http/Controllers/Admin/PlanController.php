<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * The plans, as the License Manager defines them and `plans:sync` copied them here.
     *
     * Read-only on purpose: plans, prices and what each one includes are changed in the License
     * Manager and nowhere else, so there is no form here and no route that writes a plan.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Plan::class);

        $plans = Plan::query()
            ->withCount('tenants')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->formattedPrice(),
                'services' => $plan->limitLabel($plan->max_services),
                'operators' => $plan->limitLabel($plan->max_operators),
                'bookings' => $plan->limitLabel($plan->max_bookings_per_month),
                'waiting_list' => $plan->limitLabel($plan->max_waiting_list),
                'packages' => $plan->limitLabel($plan->max_packages),
                'included' => $plan->includedLabels(),
                'is_active' => $plan->is_active,
                'portals_count' => (int) $plan->getAttribute('tenants_count'),
            ])
            ->all();

        $syncedAt = Cache::get('license.catalogue.synced-at');

        return Inertia::render('admin/plans', [
            'plans' => $plans,
            'featureLabels' => Plan::featureLabels(),
            'syncedAt' => is_string($syncedAt) ? CarbonImmutable::parse($syncedAt)->setTimezone(auth()->user()->timezone)->translatedFormat('d M Y, H:i') : null,
        ]);
    }
}
