<?php

use App\Enums\LegalDocumentType;
use App\Models\ActivityLog;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Platform Admin']);
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi']);
    $this->manager = User::factory()->manager($this->tenant)->create(['name' => 'Marco Rossi']);
});

test('the administrator publishes a new version of a document', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.legal.store', 'dpa'), [
            'version' => '2026-10',
            'file' => UploadedFile::fake()->createWithContent('dpa.pdf', '%PDF-1.4'),
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'New version published.');

    $document = LegalDocument::query()->sole();
    expect($document->type)->toBe(LegalDocumentType::DataProcessingAgreement);
    expect($document->version)->toBe('2026-10');
    Storage::disk('public')->assertExists($document->file_path);
    expect(ActivityLog::query()->where('action', 'legal.published')->sole()->user_id)->toBe($this->admin->id);
});

test('a version label is used once per document and the file must be a PDF', function () {
    Storage::fake('public');
    LegalDocument::factory()->create(['version' => '2026-10']);

    $this->actingAs($this->admin)
        ->post(route('admin.legal.store', 'dpa'), [
            'version' => '2026-10',
            'file' => UploadedFile::fake()->createWithContent('dpa.docx', 'not a pdf'),
        ])
        ->assertSessionHasErrors(['version', 'file']);

    expect(LegalDocument::query()->count())->toBe(1);
});

test('only the administrator reaches the legal documents page', function () {
    $this->actingAs($this->manager)->get(route('admin.legal'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.legal'))->assertInertia(fn (Assert $page) => $page->component('admin/legal'));
    $this->actingAs($this->manager)->post(route('admin.legal.store', 'dpa'), ['version' => 'furbo'])->assertForbidden();
});

test('an unknown document type is not found', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.legal.store', 'contratto'), ['version' => '1'])
        ->assertNotFound();
});

test('the manager is told about a binding document until the portal accepts it', function () {
    $document = LegalDocument::factory()->create(['version' => 'v1']);

    $this->actingAs($this->manager)->get(route('app.services'))
        ->assertInertia(fn (Assert $page) => $page->where('notices.0.message', 'A platform document is waiting for your acceptance: Data processing agreement.'));

    $this->post(route('app.portal.documents.accept', $document))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Document accepted.');

    $acceptance = LegalAcceptance::query()->sole();
    expect($acceptance->tenant_id)->toBe($this->tenant->id);
    expect($acceptance->user_id)->toBe($this->manager->id);
    expect($acceptance->accepted_by_name)->toBe('Marco Rossi');
    expect(ActivityLog::query()->where('action', 'legal.accepted')->sole()->tenant_id)->toBe($this->tenant->id);
    $this->actingAs($this->manager)->get(route('app.services'))->assertInertia(fn (Assert $page) => $page->where('notices', []));
});

test('a new version asks the portal again', function () {
    $old = LegalDocument::factory()->create(['version' => 'v1', 'published_at' => now()->subMonth()]);
    LegalAcceptance::factory()->for($this->tenant)->create(['legal_document_id' => $old->id]);

    LegalDocument::factory()->create(['version' => 'v2']);

    expect($this->tenant->pendingLegalDocuments()->pluck('version')->all())->toBe(['v2']);
});

test('documents to read are shown but never asked for', function () {
    LegalDocument::factory()->ofType(LegalDocumentType::SubProcessors)->create();

    expect($this->tenant->pendingLegalDocuments())->toBeEmpty();
    $this->actingAs($this->manager)->get(route('app.portal'))->assertInertia(fn (Assert $page) => $page
        ->where('documents.0.label', 'Sub-processors')
        ->where('documents.0.status', 'read'));
});

test('an outdated version cannot be accepted', function () {
    $old = LegalDocument::factory()->create(['version' => 'v1', 'published_at' => now()->subMonth()]);
    LegalDocument::factory()->create(['version' => 'v2']);

    $this->actingAs($this->manager)
        ->post(route('app.portal.documents.accept', $old))
        ->assertStatus(409);

    expect(LegalAcceptance::query()->exists())->toBeFalse();
});

test('an administrator standing in for the manager cannot accept on the portal behalf', function () {
    $document = LegalDocument::factory()->create();
    $this->actingAs($this->admin);
    app(Impersonation::class)->start($this->admin, $this->manager);

    $this->get(route('app.portal'))->assertInertia(fn (Assert $page) => $page->where('documents.0.status', 'manager_only'));

    $this->post(route('app.portal.documents.accept', $document))->assertForbidden();

    expect(LegalAcceptance::query()->exists())->toBeFalse();
});

test('the administrator sees which version each portal accepted', function () {
    $document = LegalDocument::factory()->create(['version' => 'v3']);
    LegalAcceptance::factory()->for($this->tenant)->create(['legal_document_id' => $document->id, 'accepted_by_name' => 'Marco Rossi']);
    Tenant::factory()->create(['name' => 'Studio Bianchi']);

    $this->actingAs($this->admin)
        ->get(route('admin.legal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('portals.0.name', 'Studio Bianchi')
            ->where('portals.0.accepted.'.$document->type->value.'.status', 'missing')
            ->where('portals.1.name', 'Studio Rossi')
            ->where('portals.1.accepted.'.$document->type->value.'.status', 'current')
            ->where('portals.1.accepted.'.$document->type->value.'.version', 'v3')
            ->where('portals.1.accepted.'.$document->type->value.'.accepted_by', 'Marco Rossi'));
});
