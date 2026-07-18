<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Auth\ForcePasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

final class ForcePasswordController extends BaseController
{
    public function edit(): Response
    {
        return Inertia::render('auth/force-password');
    }

    public function update(ForcePasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        return redirect()->intended(route('dashboard'));
    }
}
