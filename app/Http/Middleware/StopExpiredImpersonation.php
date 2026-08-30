<?php

namespace App\Http\Middleware;

use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StopExpiredImpersonation
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * Put the administrator back in their own shoes once the time is up.
     *
     * Checked on every request rather than by a scheduled job: the session
     * lives in the browser, and the only moment we can act on it is when that
     * browser comes back.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->impersonation->stopIfExpired();

        return $next($request);
    }
}
