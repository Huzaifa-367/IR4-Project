<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

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
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'must_change_password' => $user->must_change_password,
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ],
            ],
            'settings' => $this->sharedSettings(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{
     *     session_timeout_minutes: int,
     *     display_keep_session_alive: bool,
     *     poll_fallback_seconds: int,
     *     warning_toast_seconds: int,
     *     theme_default: string
     * }
     */
    private function sharedSettings(): array
    {
        $defaults = [
            'session_timeout_minutes' => 15,
            'display_keep_session_alive' => true,
            'poll_fallback_seconds' => 30,
            'warning_toast_seconds' => 10,
            'theme_default' => 'dark',
        ];

        try {
            $settings = app(SettingsService::class);

            return [
                'session_timeout_minutes' => max(1, (int) $settings->get('auth.session_timeout_minutes', 15)),
                'display_keep_session_alive' => (bool) $settings->get('display.keep_session_alive', true),
                'poll_fallback_seconds' => max(5, (int) $settings->get('realtime.poll_fallback_seconds', 30)),
                'warning_toast_seconds' => max(1, (int) $settings->get('alert.warning_toast_seconds', 10)),
                'theme_default' => (string) $settings->get('general.theme_default', 'dark'),
            ];
        } catch (Throwable) {
            return $defaults;
        }
    }
}
