<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingsRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SettingsService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $canonical = $this->canonicalize($key);
        $cached = Cache::remember(
            $this->cacheKey($canonical),
            now()->addMinutes(5),
            function () use ($canonical): mixed {
                $setting = Setting::query()->where('key', $canonical)->first();

                return $setting?->value;
            },
        );

        if ($cached !== null) {
            return $cached;
        }

        $definition = SettingsRegistry::get($canonical);
        if ($definition !== null) {
            return $definition['default'];
        }

        /** @var array<string, mixed> $configDefaults */
        $configDefaults = config('ir4.settings', []);

        return $configDefaults[$canonical] ?? $default;
    }

    public function set(string $key, mixed $value, ?User $actor = null, bool $confirmed = false): Setting
    {
        if (! SettingsRegistry::has($key)) {
            throw ValidationException::withMessages([
                'key' => "Unknown settings key [{$key}].",
            ]);
        }

        $definition = SettingsRegistry::get($key);
        if ($definition === null) {
            throw new InvalidArgumentException("Unknown settings key [{$key}].");
        }

        if (($definition['editable'] ?? true) === false) {
            throw ValidationException::withMessages([
                $key => 'This setting is reserved and cannot be changed.',
            ]);
        }

        if ($definition['requires_confirm'] && ! $confirmed) {
            throw ValidationException::withMessages([
                $key => 'This sensitive setting requires confirmation.',
            ]);
        }

        $normalized = $this->validateAndNormalize($key, $value, $definition);
        $old = $this->get($key);
        $actorId = $actor?->getKey() ?? auth()->id();

        $setting = Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $normalized,
                'updated_by' => $actorId,
            ],
        );

        Cache::forget($this->cacheKey($key));

        if ($old !== $normalized) {
            $this->audit->record(
                AuditEvent::ConfigChanged,
                $setting,
                "Setting {$key} changed.",
                oldValues: ['key' => $key, 'value' => $old],
                newValues: ['key' => $key, 'value' => $normalized],
                user: $actor,
            );
        }

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $values = [];
        foreach (SettingsRegistry::keys() as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $prefix): array
    {
        $values = [];
        foreach (SettingsRegistry::definitions() as $key => $definition) {
            if ($definition['group'] === $prefix || str_starts_with($key, $prefix.'.')) {
                $values[$key] = $this->get($key);
            }
        }

        return $values;
    }

    /**
     * Editor payload grouped for the Inertia settings page.
     *
     * @return list<array{key: string, label: string, settings: list<array<string, mixed>>}>
     */
    public function editorGroups(User $user): array
    {
        $rows = Setting::query()
            ->with('updatedBy:id,name')
            ->whereIn('key', SettingsRegistry::keys())
            ->get()
            ->keyBy('key');

        $grouped = [];
        foreach (SettingsRegistry::groupLabels() as $groupKey => $groupLabel) {
            $grouped[$groupKey] = [
                'key' => $groupKey,
                'label' => $groupLabel,
                'settings' => [],
            ];
        }

        foreach (SettingsRegistry::definitions() as $key => $definition) {
            $permission = $definition['permission'];
            $canEdit = ($definition['editable'] ?? true)
                && $user->can($permission);
            $row = $rows->get($key);

            $grouped[$definition['group']]['settings'][] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'] ?? null,
                'type' => $definition['type'],
                'unit' => $definition['unit'] ?? null,
                'value' => $this->get($key),
                'default' => $definition['default'],
                'min' => $definition['min'] ?? null,
                'max' => $definition['max'] ?? null,
                'options' => $definition['options'] ?? null,
                'requires_confirm' => $definition['requires_confirm'],
                'editable' => $canEdit,
                'permission' => $permission,
                'updated_at' => optional($row?->updated_at)?->toIso8601String(),
                'updated_by' => $row?->updatedBy !== null
                    ? ['id' => $row->updatedBy->id, 'name' => $row->updatedBy->name]
                    : null,
            ];
        }

        /** @var list<array{key: string, label: string, settings: list<array<string, mixed>>}> $result */
        $result = array_values(array_filter(
            $grouped,
            static fn (array $group): bool => $group['settings'] !== [],
        ));

        return $result;
    }

    /**
     * @param  array{
     *     default: mixed,
     *     type: 'bool'|'int'|'float'|'string'|'timezone'|'time'|'enum',
     *     group: string,
     *     permission: string,
     *     requires_confirm: bool,
     *     label: string,
     *     description?: string,
     *     min?: int|float,
     *     max?: int|float,
     *     options?: list<string>,
     *     editable?: bool,
     *     unit?: string
     * }  $definition
     */
    private function validateAndNormalize(string $key, mixed $value, array $definition): mixed
    {
        $type = $definition['type'];

        return match ($type) {
            'bool' => $this->asBool($key, $value),
            'int' => $this->asInt($key, $value, $definition),
            'float' => $this->asFloat($key, $value, $definition),
            'string' => $this->asString($key, $value, $definition),
            'timezone' => $this->asTimezone($key, $value),
            'time' => $this->asTime($key, $value),
            'enum' => $this->asEnum($key, $value, $definition),
        };
    }

    private function asBool(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true' || $value === 'on') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false' || $value === 'off') {
            return false;
        }

        throw ValidationException::withMessages([
            $key => 'Must be a boolean.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function asInt(string $key, mixed $value, array $definition): int
    {
        if (! is_numeric($value) || (float) $value != (int) $value) {
            throw ValidationException::withMessages([
                $key => 'Must be an integer.',
            ]);
        }

        $int = (int) $value;
        if (isset($definition['min']) && $int < $definition['min']) {
            throw ValidationException::withMessages([
                $key => "Must be at least {$definition['min']}.",
            ]);
        }
        if (isset($definition['max']) && $int > $definition['max']) {
            throw ValidationException::withMessages([
                $key => "Must be at most {$definition['max']}.",
            ]);
        }

        return $int;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function asFloat(string $key, mixed $value, array $definition): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $key => 'Must be a number.',
            ]);
        }

        $float = (float) $value;
        if (isset($definition['min']) && $float < $definition['min']) {
            throw ValidationException::withMessages([
                $key => "Must be at least {$definition['min']}.",
            ]);
        }
        if (isset($definition['max']) && $float > $definition['max']) {
            throw ValidationException::withMessages([
                $key => "Must be at most {$definition['max']}.",
            ]);
        }

        return $float;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function asString(string $key, mixed $value, array $definition): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw ValidationException::withMessages([
                $key => 'Must be a string.',
            ]);
        }

        $string = (string) $value;
        if (isset($definition['options']) && ! in_array($string, $definition['options'], true)) {
            throw ValidationException::withMessages([
                $key => 'Invalid value.',
            ]);
        }

        return $string;
    }

    private function asTimezone(string $key, mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([
                $key => 'Must be a valid timezone.',
            ]);
        }

        try {
            new \DateTimeZone($value);
        } catch (\Exception) {
            throw ValidationException::withMessages([
                $key => 'Must be a valid timezone.',
            ]);
        }

        return $value;
    }

    private function asTime(string $key, mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{2}:\d{2}$/', $value)) {
            throw ValidationException::withMessages([
                $key => 'Must be a time in HH:MM format.',
            ]);
        }

        [$hour, $minute] = array_map('intval', explode(':', $value));
        if ($hour > 23 || $minute > 59) {
            throw ValidationException::withMessages([
                $key => 'Must be a valid time.',
            ]);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function asEnum(string $key, mixed $value, array $definition): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $key => 'Must be a string.',
            ]);
        }

        $options = $definition['options'] ?? [];
        if (! in_array($value, $options, true)) {
            throw ValidationException::withMessages([
                $key => 'Invalid value.',
            ]);
        }

        return $value;
    }

    private function canonicalize(string $key): string
    {
        $legacy = SettingsRegistry::legacyMap()[$key] ?? null;

        return $legacy['key'] ?? $key;
    }

    private function cacheKey(string $key): string
    {
        return "ir4.settings.{$key}";
    }
}
