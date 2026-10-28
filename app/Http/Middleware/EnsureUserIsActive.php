<?php

namespace App\Http\Middleware;

use App\Http\Requests\Auth\LoginRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user whose account was deactivated while they were signed in,
 * on their next request, and sends them to the sign-in page with the reason.
 * Appended to the web group in bootstrap/app.php.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, LoginRequest::DEACTIVATED_MESSAGE);
            }

            return redirect()->route('login')->withErrors(['email' => LoginRequest::DEACTIVATED_MESSAGE]);
        }

        return $next($request);
    }
}
