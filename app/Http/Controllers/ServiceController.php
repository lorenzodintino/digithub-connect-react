<?php

namespace App\Http\Controllers;

use App\Actions\Services\DeleteService;
use App\Actions\Services\SaveService;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Http\Requests\ServiceRequest;
use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendaLock;
use App\Support\ServiceFormData;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * What customers can book, in the order they read it. `?edit=` opens the
     * form on one of the services.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('create', Service::class);

        $tenant = $this->tenant($request);

        $services = $tenant->services()
            ->with(['operators', 'tenant', 'packageItems.service.operators'])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(function (Service $service) use ($tenant): array {
                // Packages show the duration and price their phases add up to.
                $configured = $service->bookingConfiguration();

                return [
                    'id' => $service->id,
                    'slug' => $service->slug,
                    'name' => $service->name,
                    'description' => $service->description,
                    'color' => $service->displayColor(),
                    'duration' => $configured->formattedDuration(),
                    'location_type' => $service->location_type->value,
                    'location_label' => $service->location_type->label(),
                    'price' => $configured->formattedPrice(),
                    'requires_confirmation' => $service->requires_confirmation,
                    'is_active' => $service->is_active,
                    'operators' => $service->operators->take(4)->map(fn (User $operator) => [
                        'id' => $operator->id,
                        'name' => $operator->name,
                        'initials' => $operator->initials(),
                        'avatar' => $operator->avatarUrl(),
                    ])->values()->all(),
                    'has_operators' => $service->operators->isNotEmpty(),
                    'public_url' => route('portal.service', [$tenant, $service]),
                    'upcoming_count' => $service->upcomingBookingsCount(),
                ];
            })
            ->values()
            ->all();

        $editing = null;
        $slug = $this->filter($request, 'edit');

        if ($slug !== '') {
            $service = $tenant->services()->where('slug', $slug)->firstOrFail();
            $editing = ['slug' => $service->slug, 'id' => $service->id, 'form' => ServiceFormData::of($service)];
        }

        return Inertia::render('app/services', [
            'services' => $services,
            'editing' => $editing,
            'defaults' => ServiceFormData::defaults($tenant),
            'staff' => $tenant->users()->bookable()->orderBy('name')->get()->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name])->all(),
            'resources' => $tenant->resources()->orderBy('name')->get()->map(fn (Resource $resource) => [
                'id' => $resource->id,
                'name' => $resource->name,
                'is_active' => $resource->is_active,
            ])->all(),
            'packageServices' => $tenant->services()->where('is_package', false)->where('capacity', 1)->orderBy('name')->get()
                ->map(fn (Service $service) => ['id' => $service->id, 'name' => $service->name, 'duration' => $service->formattedDuration()])->all(),
            'locationTypes' => collect(LocationType::cases())->map(fn (LocationType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'placeholder' => $type->valuePlaceholder(),
            ])->all(),
            'questionTypes' => collect(QuestionType::cases())->map(fn (QuestionType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'needs_options' => $type->needsOptions(),
            ])->all(),
            'limits' => ['questions' => ServiceQuestion::LIMIT, 'requirements' => ServiceResourceRequirement::LIMIT],
        ]);
    }

    public function store(ServiceRequest $request, SaveService $save): RedirectResponse
    {
        $save->handle($this->tenant($request), null, $request->validated());

        Toast::success(__('Service created.'));

        return to_route('app.services');
    }

    public function update(ServiceRequest $request, Service $ownService, SaveService $save): RedirectResponse
    {
        $save->handle($this->tenant($request), $ownService, $request->validated());

        Toast::success(__('Service updated.'));

        return to_route('app.services');
    }

    /**
     * Publish or hide a service on the public page.
     */
    public function toggle(Service $ownService): RedirectResponse
    {
        Gate::authorize('update', $ownService);

        AgendaLock::forTenant($ownService->tenant_id, function () use ($ownService): void {
            $ownService->refresh()->update(['is_active' => ! $ownService->is_active]);
        });

        return back();
    }

    /**
     * Move a service to another place in the list.
     *
     * The order is the one customers read on the public page, so it is worth
     * being able to set it by hand: the service you sell most goes first.
     */
    public function reorder(Request $request, Service $ownService): RedirectResponse
    {
        Gate::authorize('update', $ownService);

        $position = $request->validate(['position' => ['required', 'integer', 'min:0']])['position'];

        $ordered = Service::query()
            ->forTenant($ownService->tenant_id)
            ->orderBy('position')
            ->orderBy('name')
            ->pluck('id')
            ->reject(fn (int $id) => $id === $ownService->id)
            ->values();

        // splice() changes $ordered in place; what it returns is what it removed.
        $ordered->splice($position, 0, [$ownService->id]);

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $index => $id) {
                Service::query()->whereKey($id)->update(['position' => $index]);
            }
        });

        return back();
    }

    public function destroy(Service $ownService, DeleteService $delete): RedirectResponse
    {
        Gate::authorize('delete', $ownService);

        $delete->handle($ownService);

        Toast::success(__('Service deleted.'));

        return back();
    }

    private function tenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->tenant === null, 404);

        return $user->tenant;
    }
}
