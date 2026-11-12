<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\PortalPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * The public page of a portal: who the business is and what can be booked.
     * A suspended portal is not there at all.
     */
    public function show(Request $request, Tenant $tenant): Response
    {
        abort_unless($tenant->isOperational(), 404);

        PortalPage::speak($request, $tenant->locale);

        $services = $tenant->services()
            ->active()
            ->with(['operators', 'tenant'])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => $service->bookingConfiguration())
            // Only what somebody can actually deliver: an empty calendar behind
            // a card is a dead end.
            ->filter(fn (Service $service) => $service->is_active && $service->operators->where('is_bookable', true)->where('is_active', true)
                ->contains(fn ($operator) => $service->operatorCanDeliver($operator)))
            ->map(fn (Service $service) => [
                'slug' => $service->slug,
                'name' => $service->name,
                'description' => $service->description,
                'color' => $service->displayColor(),
                'duration' => $service->formattedDuration(),
                'location' => ['type' => $service->location_type->value, 'label' => $service->location_type->label()],
                'price' => $service->formattedPrice(),
                'url' => route('portal.service', [$tenant, $service, ...PortalPage::keepEmbed($request)]),
            ])
            ->values()
            ->all();

        return Inertia::render('portal/show', [
            'portal' => PortalPage::layout($tenant, $request),
            'business' => [
                'description' => $tenant->description,
                'address' => $tenant->address,
                'phone' => $tenant->contact_phone,
                'website' => $tenant->website,
            ],
            'services' => $services,
        ]);
    }
}
