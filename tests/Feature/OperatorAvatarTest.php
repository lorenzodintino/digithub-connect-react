<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A real PNG: this PHP has no GD, so the fake image helper cannot be used.
 */
function fakePhoto(string $name = 'foto.png'): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent($name, $png);
}

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create(['name' => 'Giulia Bianchi']);
});

test('somebody uploads their own photo from the profile page', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::settings.profile')
        ->set('photo', fakePhoto())
        ->assertHasNoErrors();

    $path = $this->operator->fresh()->avatar_path;

    expect($path)->toStartWith('avatars/'.$this->operator->id.'/');
    Storage::disk('public')->assertExists($path);
});

test('replacing the photo deletes the one it replaces', function () {
    $component = Livewire::actingAs($this->operator)
        ->test('pages::settings.profile')
        ->set('photo', fakePhoto('prima.png'));

    $first = $this->operator->fresh()->avatar_path;

    $component->set('photo', fakePhoto('seconda.png'));

    $second = $this->operator->fresh()->avatar_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('removing the photo clears the column and the file', function () {
    $component = Livewire::actingAs($this->operator)
        ->test('pages::settings.profile')
        ->set('photo', fakePhoto());

    $path = $this->operator->fresh()->avatar_path;

    $component->call('removePhoto');

    expect($this->operator->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('only images are accepted', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::settings.profile')
        ->set('photo', UploadedFile::fake()->createWithContent('curriculum.pdf', '%PDF-1.4'))
        ->assertHasErrors('photo');

    expect($this->operator->fresh()->avatar_path)->toBeNull();
});

test('a manager uploads the photo of their operator', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('editMember', $this->operator->id)
        ->set('photo', fakePhoto())
        ->assertHasNoErrors();

    expect($this->operator->fresh()->avatar_path)->toStartWith('avatars/'.$this->operator->id.'/');
});

test('a manager cannot touch the photo of somebody outside their portal', function () {
    $stranger = User::factory()->operator()->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('editMember', $stranger->id)
        ->assertForbidden();
});

test('the public page shows the photo when there is one', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::settings.profile')
        ->set('photo', fakePhoto());

    $service = Service::factory()->for($this->tenant)->create();
    $service->operators()->attach([$this->operator->id, User::factory()->operator($this->tenant)->create()->id]);

    $this->get(route('portal.service', [$this->tenant, $service]))
        ->assertOk()
        ->assertSee($this->operator->fresh()->avatar_path, escape: false);
});
