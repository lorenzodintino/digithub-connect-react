<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\LayoutNotices;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Closures, so they are read when the page renders: by then SetLocale has
     * chosen the language and the impersonation has been checked for expiry.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'vendor' => config('app.vendor'),
            'locale' => fn () => app()->getLocale(),
            'palette' => fn () => $this->palette($request),
            'auth' => fn () => [
                'user' => $this->user($request->user()),
            ],
            'notices' => fn () => LayoutNotices::for($request->user()),
        ];
    }

    /**
     * The colour palette of the business this page belongs to: the portal in the
     * address of a public page, otherwise the business of whoever is signed in.
     * A platform administrator has no business and picks their own (users.palette). Pages that belong to nobody (sign in, the home page) have none.
     */
    private function palette(Request $request): ?string
    {
        $tenant = $request->route('tenant');

        if ($tenant instanceof Tenant) {
            return $tenant->palette->value;
        }

        $user = $request->user();

        return $user?->tenant?->palette->value ?? $user?->palette?->value;
    }

    /**
     * What the React shell knows about the person signed in.
     *
     * @return array{id: int, name: string, email: string, initials: string, avatar: string|null, role: string, tenant: array{name: string, public_url: string}|null}|null
     */
    private function user(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'avatar' => $user->avatarUrl(),
            'role' => $user->role->value,
            'tenant' => $user->tenant ? [
                'name' => $user->tenant->name,
                'public_url' => $user->tenant->publicUrl(),
            ] : null,
        ];
    }
}
