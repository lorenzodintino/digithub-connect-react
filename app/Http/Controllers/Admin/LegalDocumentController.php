<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Legal\PublishLegalDocument;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublishLegalDocumentRequest;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LegalDocumentController extends Controller
{
    /**
     * What the platform publishes towards the businesses it serves, and which
     * version each portal accepted.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LegalDocument::class);

        /** @var User $admin */
        $admin = $request->user();
        $timezone = $admin->timezone;

        $versions = LegalDocument::query()
            ->withCount('acceptances')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->toBase()
            ->groupBy(fn (LegalDocument $document): string => $document->type->value);

        $binding = collect(LegalDocumentType::cases())->filter(fn (LegalDocumentType $type): bool => $type->requiresAcceptance())->values();

        $acceptances = LegalAcceptance::query()
            ->with('document')
            ->orderByDesc('accepted_at')
            ->get()
            ->toBase()
            ->groupBy('tenant_id');

        return Inertia::render('admin/legal', [
            'documents' => collect(LegalDocumentType::cases())->map(fn (LegalDocumentType $type) => [
                'type' => $type->value,
                'label' => $type->label(),
                'requires_acceptance' => $type->requiresAcceptance(),
                'versions' => $versions->get($type->value, collect())->values()->map(fn (LegalDocument $document, int $index) => [
                    'id' => $document->id,
                    'version' => $document->version,
                    'url' => $document->url(),
                    'published_at' => $document->published_at->setTimezone($timezone)->translatedFormat('d M Y'),
                    'acceptances' => (int) $document->getAttribute('acceptances_count'),
                    'in_force' => $index === 0,
                ])->all(),
            ])->all(),
            'columns' => $binding->map(fn (LegalDocumentType $type) => ['type' => $type->value, 'label' => $type->label()])->all(),
            'portals' => Tenant::query()->orderBy('name')->get()->map(fn (Tenant $portal) => [
                'id' => $portal->id,
                'name' => $portal->name,
                'accepted' => $binding->mapWithKeys(function (LegalDocumentType $type) use ($acceptances, $versions, $portal, $timezone): array {
                    $current = $versions->get($type->value, collect())->first();
                    $acceptance = $acceptances->get($portal->getKey(), collect())
                        ->first(fn (LegalAcceptance $acceptance): bool => $acceptance->document->type === $type);

                    return [$type->value => match (true) {
                        $current === null => ['status' => 'unpublished'],
                        $acceptance === null => ['status' => 'missing'],
                        $acceptance->legal_document_id === $current->id => [
                            'status' => 'current',
                            'version' => $acceptance->document->version,
                            'accepted_at' => $acceptance->accepted_at->setTimezone($timezone)->translatedFormat('d M Y'),
                            'accepted_by' => $acceptance->accepted_by_name,
                        ],
                        default => ['status' => 'old', 'version' => $acceptance->document->version],
                    }];
                })->all(),
            ])->all(),
        ]);
    }

    /**
     * Put a new version of one document in force.
     */
    public function store(PublishLegalDocumentRequest $request, LegalDocumentType $type, PublishLegalDocument $publish): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $publish->handle($type, trim($request->string('version')->toString()), $request->file('file'), $admin);

        Toast::success(__('New version published.'));

        return back();
    }
}
