<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMobileAccountIsUsable
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            $token = $user->currentAccessToken();
            $token->delete();

            return ApiResponse::error('UNAUTHENTICATED', 'Account is inactive.', status: 401);
        }

        if ($user->must_change_password) {
            return ApiResponse::error(
                'PASSWORD_CHANGE_REQUIRED',
                'Change this password on the operator web console before using the mobile app.',
                status: 403,
            );
        }

        return $next($request);
    }
}
