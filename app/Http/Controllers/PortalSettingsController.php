<?php

namespace App\Http\Controllers;

use App\Actions\Legal\AcceptLegalDocument;
use App\Http\Requests\PortalSettingsRequest;
use App\Models\LegalDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\Activity;
use App\Support\MailSettingsData;
use App\Support\Timezones;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PortalSettingsController extends Controller
{
    /**
     * How the portal looks, how customers reach it, how its emails leave, its
     * plan, the platform documents and the export of its data.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->tenant($user);

        Gate::authorize('update', $tenant);

        return Inertia::render('app/portal', [
            'portal' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'description' => $tenant->description ?? '',
                'brand_color' => $tenant->brand_color,
                'timezone' => $tenant->timezone,
                'locale' => $tenant->locale,
                'contact_email' => $tenant->contact_email ?? '',
                'contact_phone' => $tenant->contact_phone ?? '',
                'website' => $tenant->website ?? '',
                'address' => $tenant->address ?? '',
                'privacy_policy_url' => $tenant->privacy_policy_url ?? '',
                'terms_url' => $tenant->terms_url ?? '',
                'data_controller' => $tenant->data_controller ?? '',
                'data_retention_days' => (string) ($tenant->data_retention_days ?? ''),
            ],
            'logo' => [
                'url' => $tenant->logoUrl(),
                'initials' => $tenant->initials(),
                'background' => $tenant->brand_color,
                'foreground' => $tenant->brandForeground(),
            ],
            'files' => [
                'privacy' => $tenant->privacy_policy_path ? $tenant->privacyPolicyLink() : null,
                'terms' => $tenant->terms_path ? $tenant->termsLink() : null,
            ],
            'hasLegalDocuments' => $tenant->hasLegalDocuments(),
            'publicUrl' => $tenant->publicUrl(),
            'embedSnippet' => '<iframe src="'.$tenant->publicUrl().'?embed=1" width="100%" height="760" frameborder="0" style="border:0;border-radius:12px" loading="lazy"></iframe>',
            'timezones' => Timezones::options(),
            'mail' => MailSettingsData::for($tenant, $user),
            'plan' => $this->plan($tenant),
            'documents' => $this->documents($tenant, $user),
        ]);
    }

    public function update(PortalSettingsRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->tenant($user);
        $validated = $request->validated();

        $tenant->update($validated);

        Activity::log('portal.updated', $tenant, ['fields' => array_keys($validated)]);

        Toast::success(__('Portal settings saved.'));

        return back();
    }

    /**
     * Accept the version of a platform document now in force, on behalf of
     * the portal. Only the manager in person: not an administrator standing in.
     */
    public function acceptDocument(Request $request, LegalDocument $document, AcceptLegalDocument $accept): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->tenant($user);

        Gate::authorize('accept', [$document, $tenant]);

        $accept->handle($document, $tenant, $user, $request->ip());

        Toast::success(__('Document accepted.'));

        return back();
    }

    /**
     * @return array{name: string|null, state: string|null, state_color: string|null, paid_until: string|null, usage: array<int, array{label: string, value: string}>|null}|null
     */
    private function plan(Tenant $tenant): ?array
    {
        $plan = $tenant->effectivePlan();

        // A portal the License Manager governs shows its licence, not a paid period of its own.
        $showsPaidPeriod = $tenant->paid_until !== null && ! app(LicenseService::class)->governs($tenant);

        if ($plan === null && ! $showsPaidPeriod) {
            return null;
        }

        $usage = $tenant->planUsage();
        $state = $showsPaidPeriod ? $tenant->subscriptionState() : null;

        return [
            'name' => $plan?->name,
            'state' => $state?->label(),
            'state_color' => $state?->color(),
            'paid_until' => $showsPaidPeriod ? $tenant->paid_until->translatedFormat('d M Y') : null,
            'usage' => $plan ? [
                ['label' => __('Services'), 'value' => $usage->services().' / '.$plan->limitLabel($plan->max_services)],
                ['label' => __('Operators'), 'value' => $usage->operators().' / '.$plan->limitLabel($plan->max_operators)],
                ['label' => __('Bookings this month'), 'value' => $usage->bookingsThisMonth().' / '.$plan->limitLabel($plan->max_bookings_per_month)],
            ] : null,
        ];
    }

    /**
     * The platform documents in force, each with what this portal did about it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documents(Tenant $tenant, User $user): array
    {
        $acceptances = $tenant->legalAcceptances()->get()->keyBy('legal_document_id');

        return LegalDocument::current()->values()->map(function (LegalDocument $document) use ($tenant, $user, $acceptances): array {
            $acceptance = $acceptances->get($document->getKey());

            $status = match (true) {
                ! $document->type->requiresAcceptance() => 'read',
                $acceptance !== null => 'accepted',
                $user->cannot('accept', [$document, $tenant]) => 'manager_only',
                default => 'accept',
            };

            return [
                'id' => $document->id,
                'label' => $document->type->label(),
                'url' => $document->url(),
                'version' => __('Version :version, published on :date', [
                    'version' => $document->version,
                    'date' => $document->published_at->setTimezone($tenant->timezone)->translatedFormat('d M Y'),
                ]),
                'status' => $status,
                'accepted' => $acceptance ? __('Accepted on :date by :name', [
                    'date' => $acceptance->accepted_at->setTimezone($tenant->timezone)->translatedFormat('d M Y'),
                    'name' => $acceptance->accepted_by_name,
                ]) : null,
                'confirm' => __('Accept version :version of the :document on behalf of :portal?', [
                    'version' => $document->version,
                    'document' => $document->type->label(),
                    'portal' => $tenant->name,
                ]),
            ];
        })->all();
    }

    private function tenant(User $user): Tenant
    {
        abort_if($user->tenant === null, 404);

        return $user->tenant;
    }
}
