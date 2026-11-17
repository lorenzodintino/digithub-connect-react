<?php

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
