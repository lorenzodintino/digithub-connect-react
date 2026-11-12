<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The files a portal uploads: its logo, and its privacy policy and terms as
 * PDF for the businesses with no website to publish them on. Each is stored
 * as soon as it is picked, and replaces the one before.
 */
class PortalFileController extends Controller
{
    /** @var array<string, array{column: string, directory: string, rules: array<int, string>, attribute: string, stored: string, removed: string}> */
    private const KINDS = [
        'logo' => [
            'column' => 'logo_path',
            'directory' => 'logos',
            'rules' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'attribute' => 'logo',
            'stored' => 'Logo updated.',
            'removed' => 'Logo removed.',
        ],
        // PDF only: it reads the same for everybody, and unlike an HTML or SVG
        // upload it does not run in the browser.
        'privacy' => [
            'column' => 'privacy_policy_path',
            'directory' => 'legal',
            'rules' => ['required', 'file', 'mimes:pdf', 'max:4096'],
            'attribute' => 'document',
            'stored' => 'Document uploaded.',
            'removed' => 'Document removed.',
        ],
        'terms' => [
            'column' => 'terms_path',
            'directory' => 'legal',
            'rules' => ['required', 'file', 'mimes:pdf', 'max:4096'],
            'attribute' => 'document',
            'stored' => 'Document uploaded.',
            'removed' => 'Document removed.',
        ],
    ];

    public function store(Request $request, string $kind): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $settings = self::KINDS[$kind] ?? abort(404);

        // The field is named after the kind, so the error lands under the right uploader.
        $request->validate([$kind => $settings['rules']], attributes: [$kind => __($settings['attribute'])]);

        $path = $request->file($kind)->store($settings['directory'].'/'.$tenant->getKey(), 'public');

        $this->forget($tenant->{$settings['column']});

        $tenant->update([$settings['column'] => $path]);

        Toast::success(__($settings['stored']));

        return back();
    }

    public function destroy(Request $request, string $kind): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $settings = self::KINDS[$kind] ?? abort(404);

        $this->forget($tenant->{$settings['column']});

        $tenant->update([$settings['column'] => null]);

        Toast::success(__($settings['removed']));

        return back();
    }

    /**
     * Delete a stored file, ignoring one that was never there.
     */
    private function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    private function tenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->tenant === null, 404);
        Gate::authorize('update', $user->tenant);

        return $user->tenant;
    }
}
