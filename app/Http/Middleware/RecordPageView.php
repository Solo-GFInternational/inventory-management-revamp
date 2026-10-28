<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records each page a signed-in user opens on the audit trail ("Opened
 * Inventory"): successful full-page GETs that return HTML. Form posts,
 * AJAX/JSON calls, downloads, assets and redirects aren't pages, so they
 * aren't logged. A failure to log never affects the response.
 */
class RecordPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (
            $user instanceof User
            && $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->expectsJson()
            && $request->route() !== null
            && $response->isSuccessful()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
        ) {
            AuditTrail::pageView($request, $user);
        }

        return $response;
    }
}
