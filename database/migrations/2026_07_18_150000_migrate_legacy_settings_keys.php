<?php

use App\Support\SettingsRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (SettingsRegistry::legacyMap() as $legacyKey => $map) {
            $legacy = DB::table('settings')->where('key', $legacyKey)->first();
            if ($legacy === null) {
                continue;
            }

            $canonicalExists = DB::table('settings')->where('key', $map['key'])->exists();
            if ($canonicalExists) {
                DB::table('settings')->where('key', $legacyKey)->delete();

                continue;
            }

            $value = json_decode((string) $legacy->value, true);
            if (($map['transform'] ?? null) === 'minutes_to_seconds' && is_numeric($value)) {
                $value = (int) $value * 60;
            }

            // Split the shared realtime throttle into per-stream keys when migrating.
            if ($legacyKey === 'realtime.throttle_seconds' && is_numeric($value)) {
                foreach ([
                    'realtime.headcount_throttle_seconds',
                    'realtime.positions_throttle_seconds',
                    'realtime.gas_throttle_seconds',
                ] as $streamKey) {
                    if (! DB::table('settings')->where('key', $streamKey)->exists()) {
                        DB::table('settings')->insert([
                            'key' => $streamKey,
                            'value' => json_encode((int) $value),
                            'updated_by' => $legacy->updated_by,
                            'created_at' => $legacy->created_at,
                            'updated_at' => $legacy->updated_at,
                        ]);
                    }
                }
                DB::table('settings')->where('key', $legacyKey)->delete();

                continue;
            }

            DB::table('settings')->where('key', $legacyKey)->update([
                'key' => $map['key'],
                'value' => json_encode($value),
            ]);
        }

        // Printer host/port are deploy config — remove from settings table if present.
        DB::table('settings')->whereIn('key', [
            'equipment.printer_host',
            'equipment.printer_port',
        ])->delete();
    }

    public function down(): void
    {
        // Irreversible key migration — intentionally empty.
    }
};
