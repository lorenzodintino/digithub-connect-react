<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('the home page is the showcase and carries the configured product name', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('name', config('app.name'))
            ->where('auth.user', null));
});

test('the product is called Atrio unless the environment says otherwise', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('APP_NAME=Atrio');
});

test('the home page shows a signed in person where to go', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome')->where('auth.user.id', fn ($id) => is_int($id)));
});

test('every text of the showcase has an Italian translation', function () {
    $catalogue = json_decode(file_get_contents(lang_path('it.json')), true, flags: JSON_THROW_ON_ERROR);
    $missing = [];

    foreach (File::allFiles(resource_path('js/vetrina')) as $file) {
        if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }

        preg_match_all('/\b(?:__|trans_choice)\(\s*(["\'])((?:(?!\1)[^\\\\]|\\\\.)*)\1/', $file->getContents(), $matches);

        foreach ($matches[2] as $key) {
            $key = stripcslashes($key);

            if (! array_key_exists($key, $catalogue)) {
                $missing[] = $file->getFilename().': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});

test('the plans of the home page are the ones in the table, on sale, in order', function () {
    Plan::factory()->create(['name' => 'Studio', 'position' => 3, 'price_cents' => 7900, 'max_services' => null]);
    Plan::factory()->lacking(['group_services'])->create(['name' => 'Pro', 'position' => 2, 'price_cents' => 3900, 'max_services' => 15, 'max_waiting_list' => 5]);
    Plan::factory()->lacking(['resources', 'waiting_list'])->create(['name' => 'Base', 'position' => 1, 'price_cents' => 1900, 'max_services' => 3]);
    Plan::factory()->retired()->create(['name' => 'Old', 'position' => 0]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('plans', 3)
            ->where('plans.0.name', 'Base')
            ->where('plans.0.price_cents', 1900)
            ->where('plans.0.limits.services', 3)
            ->where('plans.0.features.resources', false)
            ->where('plans.1.name', 'Pro')
            ->where('plans.1.featured', true)
            ->where('plans.1.limits.waiting_list', 5)
            ->where('plans.2.limits.services', null)
            ->where('featureLabels.resources', 'Shared resources'));
});

test('a platform with no plan on sale shows none, and the page still opens', function () {
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('plans', []));
});

test('the page says the prices of the plans, not a fixed one', function () {
    expect(file_get_contents(resource_path('js/vetrina/sections/plans.tsx')))->not->toContain('€19')
        ->and(file_get_contents(resource_path('js/vetrina/sections/hero.tsx')))->not->toContain('€19');
});
