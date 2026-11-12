<?php

namespace App\Providers;

use App\Models\Service;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->bindOwnService();
        $this->handleInertiaErrors();
    }

    /**
     * An error answered to an Inertia visit.
     *
     * The error pages stay plain Blade, readable even when the assets are
     * broken, so a page visit that fails is turned into a full visit of the
     * same address and the browser draws the Blade page. A form or a button
     * that fails (anything but GET) keeps the person where they were, with a
     * toast that says what happened: the address it posted to is not a page.
     * Full page loads, JSON and redirects are left to Laravel.
     */
    protected function handleInertiaErrors(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?Response {
            $request = $response->request;
            $status = $response->statusCode();

            if (! $request->hasHeader('X-Inertia') || $status < 400) {
                return null;
            }

            if ($request->isMethod('GET')) {
                return Inertia::location($request->fullUrl());
            }

            // A route that does not exist never reached the session middleware.
            if (! $request->hasSession()) {
                return null;
            }

            Toast::error(match ($status) {
                403 => __('Your account cannot do this.'),
                404 => __('What you were working on no longer exists.'),
                419 => __('The page sat open too long. Reload it and try again: nothing was lost.'),
                429 => __('Too Many Requests'),
                default => __('Something went wrong on our side'),
            });

            return back();
        });
    }

    /**
     * `{ownService}`: a service of the signed-in person's portal, by slug.
     *
     * Slugs are unique inside a portal, not across the platform: two portals
     * may both sell "consulenza". The plain `{service}` binding would pick the
     * first match in the whole table and hand a manager another portal's
     * service; this one only looks inside their own and answers 404 otherwise.
     */
    protected function bindOwnService(): void
    {
        Route::bind('ownService', fn (string $slug): Service => Service::query()
            ->where('tenant_id', auth()->user()?->tenant_id)
            ->where('slug', $slug)
            ->firstOrFail());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
