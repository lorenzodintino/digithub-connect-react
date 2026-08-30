<?php

namespace App\Http\Middleware;

use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the locale chosen by the user, the session or the request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['it', 'en']);

        $requested = $request->query('lang');
        $requested = is_string($requested) ? $requested : null;

        $user = $request->user();

        $locale = collect([
            $requested,
            $request->session()->get('locale'),
            $user?->locale,
            config('app.locale'),
        ])->first(fn (?string $candidate) => $candidate !== null && in_array($candidate, $supported, true));

        if ($requested !== null && $requested === $locale) {
            $request->session()->put('locale', $locale);

            // Remember the choice on the account so it follows the user across
            // devices — unless somebody is standing in for them: an
            // administrator switching language during support would otherwise
            // change the language of the customer's own account.
            if ($user !== null && $user->locale !== $locale && ! app(Impersonation::class)->isActive()) {
                $user->forceFill(['locale' => $locale])->save();
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
