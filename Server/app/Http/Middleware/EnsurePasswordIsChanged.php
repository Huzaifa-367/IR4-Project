<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordIsChanged
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('force-password.*', 'logout', 'login', 'login.store')) {
            return $next($request);
        }

        if ($request->is('force-password', 'logout')) {
            return $next($request);
        }

        return redirect()->route('force-password.edit');
    }
}
