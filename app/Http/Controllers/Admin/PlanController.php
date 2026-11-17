<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Plans\SavePlan;
use App\Enums\LicenseEnforcement;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Services\LicenseService;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * The plans on offer, in the order they are meant to be read.
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
                'can_delete' => Gate::allows('delete', $plan),
                'form' => [
                    'name' => $plan->name,
                    'description' => $plan->description ?? '',
                    'price' => $plan->price_cents === null ? '' : (string) ($plan->price_cents / 100),
                    'max_services' => (string) ($plan->max_services ?? ''),
                    'max_operators' => (string) ($plan->max_operators ?? ''),
                    'max_bookings_per_month' => (string) ($plan->max_bookings_per_month ?? ''),
                    'max_waiting_list' => (string) ($plan->max_waiting_list ?? ''),
                    'max_packages' => (string) ($plan->max_packages ?? ''),
                    'features' => $plan->featureMap(),
                    'is_active' => $plan->is_active,
                    'position' => (string) $plan->position,
                ],
            ])
            ->all();

        $syncedAt = Cache::get('license.catalogue.synced-at');

        return Inertia::render('admin/plans', [
            'plans' => $plans,
            'featureLabels' => Plan::featureLabels(),
            // With the License Manager in charge the plans are its: this page shows them and does not change them.
            'catalogue' => [
                'managed' => $this->managed(),
                'synced_at' => is_string($syncedAt) ? CarbonImmutable::parse($syncedAt)->setTimezone(auth()->user()->timezone)->translatedFormat('d M Y, H:i') : null,
            ],
        ]);
    }

    /**
     * Whether the plans come from the License Manager: then they are read-only here.
     */
    private function managed(): bool
    {
        return app(LicenseService::class)->enforcement() === LicenseEnforcement::On;
    }

    public function store(PlanRequest $request, SavePlan $save): RedirectResponse
    {
        abort_if($this->managed(), 403);

        $save->create($request->validated());

        Toast::success(__('Plan created.'));

        return back();
    }

    public function update(PlanRequest $request, Plan $plan, SavePlan $save): RedirectResponse
    {
        abort_if($this->managed(), 403);

        $save->update($plan, $request->validated());

        Toast::success(__('Plan updated.'));

        return back();
    }

    /**
     * Take a plan off the price list, or put it back, without touching the
     * portals on it.
     */
    public function toggle(Plan $plan): RedirectResponse
    {
        Gate::authorize('update', $plan);
        abort_if($this->managed(), 403);

        $plan->update(['is_active' => ! $plan->is_active]);

        Toast::success($plan->is_active ? __('Plan is on sale again.') : __('Plan retired.'));

        return back();
    }

    /**
     * Delete a plan nobody is on; the policy refuses the others.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        Gate::authorize('delete', $plan);
        abort_if($this->managed(), 403);

        $plan->delete();

        Toast::success(__('Plan deleted.'));

        return back();
    }
}
