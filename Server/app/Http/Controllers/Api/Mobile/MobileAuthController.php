<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthLockoutService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MobileAuthController extends Controller
{
    public function login(Request $request, AuthLockoutService $lockout, SettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $email = Str::lower($validated['email']);

        if ($lockout->isLocked($email)) {
            throw ValidationException::withMessages([
                'email' => [__('Account temporarily locked. Try again later.')],
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            $lockout->recordFailure($email);

            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        if (! $user->is_active) {
            $lockout->recordFailure($email);

            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        if ($user->must_change_password) {
            throw ValidationException::withMessages([
                'email' => [__('Change this password on the operator web console before using the mobile app.')],
            ]);
        }

        if (
            (bool) $settings->get('auth.require_2fa_for_admins', false)
            && $user->can('view-users')
            && $user->two_factor_confirmed_at === null
        ) {
            throw ValidationException::withMessages([
                'email' => [__('Two-factor authentication is required for administrator accounts.')],
            ]);
        }

        $lockout->clearFailures($email);

        $deviceName = filled($validated['device_name'] ?? null)
            ? (string) $validated['device_name']
            : 'IR4 Mobile';

        $token = $user->createToken($deviceName)->plainTextToken;

        event(new Login('sanctum', $user, false));

        return ApiResponse::ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }

        return ApiResponse::ok(['logged_out' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok([
            'user' => $this->userPayload($user),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'must_change_password' => $user->must_change_password,
        ];
    }
}
