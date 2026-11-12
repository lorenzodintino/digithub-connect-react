<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tenants\DeletePortal;
use App\Actions\Tenants\SavePortal;
use App\Enums\SubscriptionState;
use App\Http\Controllers\Controller;
use App\Http\Requests\PortalRequest;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use App\Services\LicenseService;
use App\Support\BillingData;
use App\Support\LicenseMessages;
use App\Support\MailSettingsData;
use App\Support\Search;
use App\Support\Timezones;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Every business on the platform, ten a page, with what the edit form of
     * each needs: the list is short and the form opens without a round trip.
     */
    public function index(Request $request, LicenseService $license): Response
    {
        Gate::authorize('viewAny', Tenant::class);

        $search = $this->filter($request, 'q');
        $status = in_array($this->filter($request, 'status'), ['active', 'suspended'], true) ? $this->filter($request, 'status') : 'all';

        $portals = Tenant::query()
            ->withCount(['services', 'users', 'bookings as upcoming_count' => fn ($query) => $query->blocking()->upcoming()])
            ->with(['manager', 'billingProfile', 'license'])
            ->when($search !== '', fn ($query) => Search::contains($query, ['name', 'slug', 'contact_email', 'license_id'], $search))
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Tenant $portal) => [
                'json_gaps' => BillingData::gaps($portal->billingProfile),
                'license_id' => $portal->license_id,
                'plan_locked' => $license->governs($portal) && $portal->license?->plan_name !== null,
                'license_plan' => $portal->license?->plan_name,
                'id' => $portal->id,
                'slug' => $portal->slug,
                'name' => $portal->name,
                'logo' => $portal->logoUrl(),
                'initials' => $portal->initials(),
                'brand_color' => $portal->brand_color,
                'public_url' => $portal->publicUrl(),
                'manager' => $portal->manager ? ['name' => $portal->manager->name, 'email' => $portal->manager->email] : null,
                'services_count' => (int) $portal->getAttribute('services_count'),
                'users_count' => (int) $portal->getAttribute('users_count'),
                'upcoming_count' => (int) $portal->getAttribute('upcoming_count'),
                'is_active' => $portal->is_active,
                'mail_failing' => $portal->mailDeliveryFailing(),
                'form' => [
                    'name' => $portal->name,
                    'slug' => $portal->slug,
                    'contact_email' => $portal->contact_email ?? '',
                    'contact_phone' => $portal->contact_phone ?? '',
                    'website' => $portal->website ?? '',
                    'address' => $portal->address ?? '',
                    'timezone' => $portal->timezone,
                    'locale' => $portal->locale,
                    'brand_color' => $portal->brand_color,
                    'plan_id' => (string) ($portal->plan_id ?? ''),
                    'description' => $portal->description ?? '',
                    'is_active' => $portal->is_active,
                    'billing' => BillingData::formValues($portal->billingProfile),
                ],
            ]);

        return Inertia::render('admin/portals', [
            'portals' => $portals,
            'filters' => ['q' => $search, 'status' => $status],
            'plans' => Plan::query()->orderBy('position')->orderBy('name')->get()->map(fn (Plan $plan) => ['id' => (string) $plan->id, 'name' => $plan->name])->all(),
            'timezones' => Timezones::options(),
            'licenseSoftwareSet' => filled(config('services.license.software')),
        ]);
    }

    /**
     * Provision a portal and hand the administrator the manager's credentials,
     * once: flash data never lands in the browser history.
     */
    public function store(PortalRequest $request, SavePortal $save): RedirectResponse
    {
        ['manager' => $manager, 'password' => $password] = $save->create($request->validated());

        Inertia::flash('credentials', ['email' => $manager->email, 'password' => $password]);

        return back();
    }

    public function update(PortalRequest $request, Tenant $tenant, SavePortal $save): RedirectResponse
    {
        $save->update($tenant, $request->validated());

        Toast::success(__('Portal updated.'));

        return back();
    }

    /**
     * Suspend or reactivate a portal by hand.
     */
    public function toggle(Tenant $tenant): RedirectResponse
    {
        Gate::authorize('update', $tenant);

        $tenant->update(['is_active' => ! $tenant->is_active]);

        Toast::success($tenant->is_active ? __('Portal activated.') : __('Portal suspended.'));

        return back();
    }

    public function destroy(Tenant $tenant, DeletePortal $delete): RedirectResponse
    {
        Gate::authorize('delete', $tenant);

        $delete->handle($tenant);

        Toast::success(__('Portal deleted.'));

        return to_route('admin.portals');
    }

    /**
     * One portal from the platform's side: who runs it, what it sells, its
     * plan and payment, and how its emails leave.
     */
    public function show(Request $request, Tenant $tenant, LicenseService $license): Response
    {
        Gate::authorize('view', $tenant);
        Gate::authorize('manageBilling', $tenant);

        /** @var User $admin */
        $admin = $request->user();
        $manager = $tenant->manager()->first();
        $state = $tenant->subscriptionState();

        $services = $tenant->services()
            ->with(['tenant', 'packageItems.service.operators'])
            ->withCount('operators')
            ->orderBy('position')
            ->get()
            ->map(fn (Service $service) => $service->bookingConfiguration())
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'color' => $service->displayColor(),
                'duration' => $service->formattedDuration(),
                'operators_count' => (int) $service->getAttribute('operators_count'),
                'is_active' => $service->is_active,
            ])
            ->values()
            ->all();

        $operators = $tenant->operators()
            ->withCount('availabilityRules')
            ->orderBy('name')
            ->get()
            ->map(fn (User $operator) => [
                'id' => $operator->id,
                'name' => $operator->name,
                'email' => $operator->email,
                'initials' => $operator->initials(),
                'avatar' => $operator->avatarUrl(),
                'weekly_slots' => (int) $operator->getAttribute('availability_rules_count'),
                'is_active' => $operator->is_active,
            ])
            ->all();

        return Inertia::render('admin/portal', [
            'portal' => [
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'description' => $tenant->description,
                'is_active' => $tenant->is_active,
                'public_url' => $tenant->publicUrl(),
                'timezone' => $tenant->timezone,
                'contact' => [
                    'email' => $tenant->contact_email,
                    'phone' => $tenant->contact_phone,
                    'website' => $tenant->website,
                    'address' => $tenant->address,
                ],
            ],
            'manager' => $manager === null ? null : [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
                'initials' => $manager->initials(),
                'avatar' => $manager->avatarUrl(),
            ],
            'impersonationMinutes' => Impersonation::MINUTES,
            'billing' => $this->billing($tenant),
            'license' => $this->license($tenant, $license),
            'plan' => $this->plan($tenant),
            'subscription' => [
                'governed' => $license->governs($tenant),
                'paid_until' => $tenant->paid_until?->toDateString() ?? '',
                'label' => $state->label(),
                'color' => $state->color(),
                'suspended_for_payment' => $tenant->subscription_suspended_at !== null,
                'suspends_on' => $tenant->suspendsOn() !== null && in_array($state, [SubscriptionState::Expiring, SubscriptionState::Grace], true)
                    ? $tenant->suspendsOn()->translatedFormat('d M Y')
                    : null,
            ],
            'mail' => MailSettingsData::for($tenant, $admin),
            'services' => $services,
            'operators' => $operators,
        ]);
    }

    /**
     * Who pays for the portal, what is still missing to invoice it, and the
     * block to copy into the License Manager together with the portal's id,
     * which is what that software knows the portal by (the slug can change).
     *
     * @return array{tenant_id: string, complete: bool, gaps: array<int, string>, kind: string|null, values: array<string, string>, text: string}
     */
    private function billing(Tenant $tenant): array
    {
        $profile = $tenant->billingProfile;
        $gaps = BillingData::gaps($profile);

        return [
            'tenant_id' => (string) $tenant->license_id,
            'complete' => $gaps === [],
            'gaps' => $gaps,
            'kind' => $profile?->kind?->label(),
            'values' => BillingData::formValues($profile),
            'text' => BillingData::clipboardText($tenant, $profile),
        ];
    }

    /**
     * The licence of the portal for the administrator: the verdict of the
     * License Manager as last known, whether it governs the portal, and what
     * the plan name maps to here. The amount still to be collected is shown
     * to the administrator only; the customer record is not kept at all.
     *
     * @return array<string, mixed>
     */
    private function license(Tenant $tenant, LicenseService $license): array
    {
        $status = $license->status($tenant);
        $timezone = $tenant->timezone;
        $mapped = $license->mappedPlan($status);
        $blockKey = $status->blockKey();

        return [
            'enforcement' => $license->enforcement()->value,
            'configuration_problem' => $license->configurationProblem(),
            'exempt' => $tenant->license_exempt,
            'governed' => $license->governs($tenant),
            'state' => LicenseMessages::state($status),
            'color' => LicenseMessages::color($status),
            'expires_at' => $status->lifetime ? null : $status->expiresAt?->setTimezone($timezone)->translatedFormat('d M Y'),
            'lifetime' => $status->lifetime,
            'grace_ends_at' => $status->graceEndsAt?->setTimezone($timezone)->translatedFormat('d M Y'),
            'plan' => $status->planName,
            'local_plan' => $mapped?->name,
            'plan_unknown' => $status->planName !== null && $mapped === null,
            'checked_at' => $status->checkedAt?->setTimezone($timezone)->translatedFormat('d M Y H:i'),
            'stale' => $status->stale,
            'alarm' => $status->alarm,
            'error' => $status->error,
            'balance' => LicenseMessages::money($status->outstandingCents, $status->currency),
            'would_block' => $blockKey === null ? null : LicenseMessages::reason($blockKey),
        ];
    }

    /**
     * The plan of the portal and how much of it is used, as the detail page
     * prints it.
     *
     * @return array<string, mixed>|null
     */
    private function plan(Tenant $tenant): ?array
    {
        $plan = $tenant->effectivePlan();

        if ($plan === null) {
            return null;
        }

        $usage = $tenant->planUsage();

        return [
            'name' => $plan->name,
            'from_license' => app(LicenseService::class)->governs($tenant),
            'price' => $plan->formattedPrice(),
            'services' => $usage->services().' / '.$plan->limitLabel($plan->max_services),
            'operators' => $usage->operators().' / '.$plan->limitLabel($plan->max_operators),
            'bookings' => $usage->bookingsThisMonth().' / '.$plan->limitLabel($plan->max_bookings_per_month),
            'bookings_threshold' => match (true) {
                $usage->bookingsAtLimit() => 'reached',
                $usage->bookingsNearLimit() => 'near',
                default => null,
            },
            'own_smtp' => $plan->allows_custom_smtp,
        ];
    }
}
