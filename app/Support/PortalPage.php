<?php

namespace App\Support;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * What every public page of a portal shares: the brand of the business, the
 * language it speaks to a visitor nobody has asked yet, and the `?embed=1`
 * of a page drawn inside the business's own website.
 */
class PortalPage
{
    /**
     * Speak the portal's language unless the visitor picked one: in this
     * session, or with `?lang=` on this very request.
     */
    public static function speak(Request $request, string $locale): void
    {
        if (! $request->session()->has('locale') && ! $request->filled('lang')) {
            app()->setLocale($locale);
        }
    }

    /**
     * Whether the page is drawn inside an iframe on the business's website,
     * without our header and footer.
     */
    public static function embedded(Request $request): bool
    {
        return $request->boolean('embed');
    }

    /**
     * Route parameters that keep `?embed=1` on the next page.
     *
     * @return array<string, int>
     */
    public static function keepEmbed(Request $request): array
    {
        return self::embedded($request) ? ['embed' => 1] : [];
    }

    /**
     * The layout of the public pages: header, footer and brand colours.
     *
     * @return array<string, mixed>
     */
    public static function layout(Tenant $tenant, Request $request): array
    {
        return [
            'name' => $tenant->name,
            'home_url' => route('portal.show', [$tenant, ...self::keepEmbed($request)]),
            'logo' => $tenant->logoUrl(),
            'initials' => $tenant->initials(),
            'brand' => $tenant->brand_color,
            'brand_contrast' => $tenant->brandForeground(),
            'privacy_url' => $tenant->privacyPolicyLink(),
            'terms_url' => $tenant->termsLink(),
            'embed' => self::embedded($request),
            'year' => now()->year,
        ];
    }

    /**
     * Seconds to wait before this visitor may book again, or null.
     *
     * Nothing else guards the public forms: without a limit a script can fill
     * an agenda with junk appointments, or a waiting list with junk names, and
     * fire the emails that go with them. Five a minute and twenty an hour per
     * service and address.
     */
    public static function throttle(Service $service, Request $request): ?int
    {
        $key = 'booking:'.$service->id.':'.$request->ip();

        foreach ([[$key.':minute', 5, 60], [$key.':hour', 20, 3600]] as [$bucket, $attempts, $decay]) {
            if (RateLimiter::tooManyAttempts($bucket, $attempts)) {
                return RateLimiter::availableIn($bucket);
            }

            RateLimiter::hit($bucket, $decay);
        }

        return null;
    }
}
