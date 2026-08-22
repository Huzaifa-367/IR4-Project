<?php

namespace App\Support;

/**
 * Legacy dummy EPCs from pre-registry standby (E280… / IR4W / IR4S). Never commission these.
 */
final class LegacyRfidTagUids
{
    public static function isLegacy(string $tagUid): bool
    {
        $normalized = strtoupper(trim($tagUid));

        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, 'E280')) {
            return true;
        }

        return in_array($normalized, self::known(), true);
    }

    /**
     * @return list<string>
     */
    public static function known(): array
    {
        return [
            'E280116060000203IR4W0001',
            'E280116060000203IR4W0002',
            'E280116060000203IR4W0003',
            'E280116060000203IR4S0001',
            'E280116060000203IR4S0002',
            'E280116060000203IR4S0003',
            'E280116060000203IR4S0004',
        ];
    }

    /** 1-based IR4W/IR4S suffix → site registry EPC. */
    public static function replacementEpc(string $legacyUid): string
    {
        $normalized = strtoupper(trim($legacyUid));

        if (preg_match('/IR4[WS](\d+)$/', $normalized, $matches) === 1) {
            return SiteRfidTags::at(max(1, (int) $matches[1]));
        }

        return SiteRfidTags::at(1);
    }
}
