<?php

namespace App\Services\Platform;

use App\Mail\TechTeamAlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Parallel ops channel for plant health (cameras, servers, backups, disk).
 * Does not write to `alerts` — operator AlertService stays untouched (DOC-07).
 */
final class TechTeamNotifier
{
    private const CACHE_PREFIX = 'ir4:tech_team_mail:';

    /**
     * Mail the tech list once per open condition. Empty MAIL_TECH_TO = no-op.
     *
     * @param  array<string, mixed>  $context
     */
    public function notify(string $dedupeKey, string $subject, array $context = []): void
    {
        $to = config('ir4.infrastructure.tech_mail_to');
        if (! is_array($to) || $to === []) {
            return;
        }

        $cacheKey = self::CACHE_PREFIX.$dedupeKey;
        if (! Cache::add($cacheKey, true, now()->addDays(7))) {
            return;
        }

        try {
            Mail::to($to)->send(new TechTeamAlertMail($subject, $context, $dedupeKey));
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);
            Log::error('ir4.tech_team.mail_failed', [
                'dedupe_key' => $dedupeKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function clear(string $dedupeKey): void
    {
        Cache::forget(self::CACHE_PREFIX.$dedupeKey);
    }
}
