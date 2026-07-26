<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnforceIdleTimeout
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) $this->settings->get('auth.session_timeout_minutes', 15);
        $lastActivity = $request->session()->get('last_activity_at');

        if (is_numeric($lastActivity)) {
            $idleSeconds = now()->getTimestamp() - (int) $lastActivity;

            if ($idleSeconds > ($timeoutMinutes * 60)) {
                $request->attributes->set('logout_reason', 'idle_timeout');
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->guest(route('login', ['timeout' => 1]));
            }
        }

        $request->session()->put('last_activity_at', now()->getTimestamp());

        return $next($request);
    }
}
