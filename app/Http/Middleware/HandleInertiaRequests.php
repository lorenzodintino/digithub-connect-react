<?php

namespace App\Http\Middleware;

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
            'locale' => fn () => app()->getLocale(),
            'auth' => fn () => [
                'user' => $this->user($request->user()),
            ],
            'notices' => fn () => LayoutNotices::for($request->user()),
        ];
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
