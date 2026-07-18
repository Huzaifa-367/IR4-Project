<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserProvisioningService;
use App\Support\PermissionCatalogue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends BaseController
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role' => $user->primaryRole()?->name,
            ]);

        $roles = Role::query()
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->orderBy('name')
            ->get(['id', 'name', 'is_system', 'is_read_only']);

        return Inertia::render('settings/users/index', [
            'users' => $users,
            'roles' => $roles,
            'temporaryPassword' => session()->pull('temporary_password'),
        ]);
    }

    public function store(StoreUserRequest $request, UserProvisioningService $users): RedirectResponse
    {
        $result = $users->create($request->validated());

        return redirect()
            ->route('settings.users.index')
            ->with('temporary_password', [
                'user_id' => $result['user']->id,
                'user_name' => $result['user']->name,
                'email' => $result['user']->email,
                'password' => $result['temporary_password'],
            ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserProvisioningService $users): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        if (isset($data['role'])) {
            /** @var Role $role */
            $role = Role::query()
                ->where('name', $data['role'])
                ->where('guard_name', PermissionCatalogue::GUARD)
                ->firstOrFail();
            $users->assignRole($user, $role);
        }

        if (array_key_exists('is_active', $data)) {
            if ($data['is_active'] === false) {
                $users->deactivate($user);
            } else {
                $user->forceFill(['is_active' => true])->save();
            }
        }

        if (! empty($data['name'])) {
            $user->forceFill(['name' => $data['name']])->save();
        }

        return redirect()->route('settings.users.index');
    }
}
