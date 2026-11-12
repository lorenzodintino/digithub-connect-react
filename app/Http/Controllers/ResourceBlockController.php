<?php

namespace App\Http\Controllers;

use App\Actions\Resources\BlockResource;
use App\Http\Requests\ResourceBlockRequest;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ResourceBlockController extends Controller
{
    public function store(ResourceBlockRequest $request, Resource $resource, BlockResource $block): RedirectResponse
    {
        $block->handle($resource, $request->validated('starts_at'), $request->validated('ends_at'), $request->validated('reason'));

        Toast::success(__('Resource unavailability saved.'));

        return back();
    }

    public function destroy(ResourceBlock $block, BlockResource $blocks): RedirectResponse
    {
        Gate::authorize('update', $block->resource);

        $blocks->remove($block);

        Toast::success(__('Resource unavailability removed.'));

        return back();
    }
}
