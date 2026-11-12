<?php

namespace App\Http\Controllers;

use App\Actions\Resources\DeleteResource;
use App\Actions\Resources\SaveResource;
use App\Enums\ResourceType;
use App\Http\Requests\ResourceRequest;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    /**
     * Rooms, cabins and equipment shared by the services of the portal, with
     * their upcoming unavailability in the portal's timezone.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Resource::class);

        $tenant = $this->tenant($request);

        $resources = $tenant->resources()
            ->with(['blocks' => fn ($query) => $query->where('ends_at', '>', now())->orderBy('starts_at')])
            ->orderBy('name')
            ->get()
            ->map(fn (Resource $resource) => [
                'id' => $resource->id,
                'name' => $resource->name,
                'type' => $resource->type->value,
                'type_label' => $resource->type->label(),
                'description' => $resource->description ?? '',
                'notes' => $resource->notes ?? '',
                'preparation_minutes' => $resource->preparation_minutes,
                'cleanup_minutes' => $resource->cleanup_minutes,
                'is_active' => $resource->is_active,
                'blocks' => $resource->blocks->map(fn (ResourceBlock $block) => [
                    'id' => $block->id,
                    'period' => $block->starts_at->setTimezone($tenant->timezone)->format('d/m/Y H:i').' — '.$block->ends_at->setTimezone($tenant->timezone)->format('d/m/Y H:i'),
                    'reason' => $block->reason,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('app/resources', [
            'resources' => $resources,
            'types' => collect(ResourceType::cases())->map(fn (ResourceType $type) => ['value' => $type->value, 'label' => $type->label()])->all(),
            'timezone' => $tenant->timezone,
        ]);
    }

    public function store(ResourceRequest $request, SaveResource $save): RedirectResponse
    {
        $save->handle($this->tenant($request), null, $request->validated());

        Toast::success(__('Resource created.'));

        return back();
    }

    public function update(ResourceRequest $request, Resource $resource, SaveResource $save): RedirectResponse
    {
        $save->handle($this->tenant($request), $resource, $request->validated());

        Toast::success(__('Resource updated.'));

        return back();
    }

    public function destroy(Resource $resource, DeleteResource $delete): RedirectResponse
    {
        Gate::authorize('delete', $resource);

        $delete->handle($resource);

        Toast::success(__('Resource deleted.'));

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
