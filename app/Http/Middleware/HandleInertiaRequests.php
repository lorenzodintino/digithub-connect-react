<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LayoutNotices;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Closures, so they are read when the page renders: by then SetLocale has
     * chosen the language and the impersonation has been checked for expiry.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'vendor' => config('app.vendor'),
            'locale' => fn () => app()->getLocale(),
            'palette' => fn () => $this->palette($request),
            // For administrators only: how many requests for a bigger plan wait for an answer.
            'planRequestsOpen' => fn () => $request->user()?->isAdmin() ? PlanRequest::query()->open()->count() : null,
            'auth' => fn () => [
                'user' => $this->user($request->user()),
            ],
            'notices' => fn () => LayoutNotices::for($request->user()),
        ];
    }

    /**
     * The colour palette of the business this page belongs to: the portal in the
     * address of a public page, otherwise the business of whoever is signed in.
     * A platform administrator has no business and picks their own (users.palette). Pages that belong to nobody (sign in, the home page) have none.
     */
    private function palette(Request $request): ?string
    {
        $tenant = $request->route('tenant');

        if ($tenant instanceof Tenant) {
            return $tenant->palette->value;
        }

        $user = $request->user();

        return $user?->tenant?->palette->value ?? $user?->palette?->value;
    }

    /**
     * The plan in force and the ones the manager can ask for, with what each includes.
     *
     * @return array{name: string|null, upgrades: array<int, array<string, mixed>>}
     */
    private function plan(Tenant $tenant): array
    {
        return [
            'name' => $tenant->effectivePlan()?->name,
            'openRequest' => $this->openRequest($tenant),
            'upgrades' => $tenant->upgradePlans()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'services' => $plan->limitLabel($plan->max_services),
                'operators' => $plan->limitLabel($plan->max_operators),
                'bookings' => $plan->limitLabel($plan->max_bookings_per_month),
                'waiting_list' => $plan->limitLabel($plan->max_waiting_list),
                'packages' => $plan->limitLabel($plan->max_packages),
                'includes' => $plan->includedLabels(),
            ])->values()->all(),
        ];
    }

    /**
     * The request for a bigger plan still waiting for an answer, if the portal has one.
     *
     * @return array{id: int, status: string, label: string, requested: string, color: string}|null
     */
    private function openRequest(Tenant $tenant): ?array
    {
        $request = $tenant->planRequests()->open()->latest('id')->first();

        return $request === null ? null : [
            'id' => $request->id,
            'status' => $request->status->value,
            'label' => $request->status->label(),
            'requested' => $request->requested_plan,
            'color' => $request->status->color(),
        ];
    }

    /**
     * What the React shell knows about the person signed in.
     *
     * @return array{id: int, name: string, email: string, initials: string, avatar: string|null, role: string, tenant: array{name: string, public_url: string, features: array<string, bool>, plan: array<string, mixed>|null}|null}|null
     */
    private function user(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'avatar' => $user->avatarUrl(),
            'role' => $user->role->value,
            'tenant' => $user->tenant ? [
                'name' => $user->tenant->name,
                'public_url' => $user->tenant->publicUrl(),
                'features' => $user->tenant->planFeatures(),
                // What a manager can ask for: the plans above the current one (never prices: those are for the administrator).
                'plan' => $user->isManager() ? $this->plan($user->tenant) : null,
            ] : null,
        ];
    }
}
