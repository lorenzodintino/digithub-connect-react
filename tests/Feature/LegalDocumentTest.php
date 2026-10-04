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
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Platform Admin']);
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi']);
    $this->manager = User::factory()->manager($this->tenant)->create(['name' => 'Marco Rossi']);
});

test('the administrator publishes a new version of a document', function () {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test('pages::admin.legal')
        ->call('startPublishing', 'dpa')
        ->set('version', '2026-10')
        ->set('file', UploadedFile::fake()->createWithContent('dpa.pdf', '%PDF-1.4'))
        ->call('publish')
        ->assertHasNoErrors();

    $document = LegalDocument::query()->sole();
    expect($document->type)->toBe(LegalDocumentType::DataProcessingAgreement);
    expect($document->version)->toBe('2026-10');
    Storage::disk('public')->assertExists($document->file_path);
    expect(ActivityLog::query()->where('action', 'legal.published')->sole()->user_id)->toBe($this->admin->id);
});

test('a version label is used once per document and the file must be a PDF', function () {
    Storage::fake('public');
    LegalDocument::factory()->create(['version' => '2026-10']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.legal')
        ->call('startPublishing', 'dpa')
        ->set('version', '2026-10')
        ->set('file', UploadedFile::fake()->createWithContent('dpa.docx', 'not a pdf'))
        ->call('publish')
        ->assertHasErrors(['version' => 'unique', 'file' => 'mimes']);

    expect(LegalDocument::query()->count())->toBe(1);
});

test('only the administrator reaches the legal documents page', function () {
    $this->actingAs($this->manager)->get(route('admin.legal'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.legal'))->assertOk()->assertSee('Acceptance by portal');
});

test('the manager is told about a binding document until the portal accepts it', function () {
    $document = LegalDocument::factory()->create(['version' => 'v1']);

    $this->actingAs($this->manager)->get(route('app.services'))
        ->assertSee('A platform document is waiting for your acceptance: Data processing agreement.');

    Livewire::actingAs($this->manager)
        ->test('pages::app.portal')
        ->call('acceptDocument', $document->id)
        ->assertHasNoErrors();

    $acceptance = LegalAcceptance::query()->sole();
    expect($acceptance->tenant_id)->toBe($this->tenant->id);
    expect($acceptance->user_id)->toBe($this->manager->id);
    expect($acceptance->accepted_by_name)->toBe('Marco Rossi');
    expect(ActivityLog::query()->where('action', 'legal.accepted')->sole()->tenant_id)->toBe($this->tenant->id);
    $this->actingAs($this->manager)->get(route('app.services'))->assertDontSee('waiting for your acceptance');
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
    Livewire::actingAs($this->manager)->test('pages::app.portal')->assertSee('Sub-processors')->assertSee('To read');
});

test('an outdated version cannot be accepted', function () {
    $old = LegalDocument::factory()->create(['version' => 'v1', 'published_at' => now()->subMonth()]);
    LegalDocument::factory()->create(['version' => 'v2']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.portal')
        ->call('acceptDocument', $old->id)
        ->assertStatus(409);

    expect(LegalAcceptance::query()->exists())->toBeFalse();
});

test('an administrator standing in for the manager cannot accept on the portal behalf', function () {
    $document = LegalDocument::factory()->create();
    $this->actingAs($this->admin);
    app(Impersonation::class)->start($this->admin, $this->manager);

    Livewire::actingAs($this->manager)
        ->test('pages::app.portal')
        ->assertSee('The manager has to accept it personally')
        ->call('acceptDocument', $document->id)
        ->assertForbidden();

    expect(LegalAcceptance::query()->exists())->toBeFalse();
});

test('the administrator sees which version each portal accepted', function () {
    $document = LegalDocument::factory()->create(['version' => 'v3']);
    LegalAcceptance::factory()->for($this->tenant)->create(['legal_document_id' => $document->id, 'accepted_by_name' => 'Marco Rossi']);
    Tenant::factory()->create(['name' => 'Studio Bianchi']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.legal')
        ->assertSeeInOrder(['Studio Bianchi', 'Not accepted', 'Studio Rossi', 'v3', 'Marco Rossi']);
});
