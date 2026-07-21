<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Settings\UpdateGeneralSettingsRequest;
use App\Services\SettingsService;
use App\Support\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GeneralSettingsController extends BaseController
{
    public function edit(Request $request, SettingsService $settings): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                $user->can('view-settings')
                || $user->can('update-settings')
                || $user->can('update-alert-settings')
                || $user->can('view-gas-thresholds')
                || $user->can('update-gas-thresholds')
            ),
            403,
        );

        return Inertia::render('settings/general/index', [
            'groups' => $settings->editorGroups($user),
            'gasThresholdsUrl' => route('gas.thresholds.index'),
        ]);
    }

    public function update(
        UpdateGeneralSettingsRequest $request,
        SettingsService $settings,
    ): RedirectResponse {
        $data = $request->validated();
        /** @var array<string, mixed> $values */
        $values = $data['settings'] ?? [];
        /** @var list<string> $confirmed */
        $confirmed = $data['confirmed'] ?? [];

        foreach ($values as $rawKey => $value) {
            $key = (string) $rawKey;
            if (! SettingsRegistry::has($key)) {
                continue;
            }

            $definition = SettingsRegistry::get($key);
            if ($definition === null) {
                continue;
            }

            abort_unless($request->user()?->can($definition['permission']) ?? false, 403);

            $settings->set(
                $key,
                $value,
                $request->user(),
                confirmed: in_array($key, $confirmed, true),
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Settings saved.',
        ]);

        return back();
    }
}
