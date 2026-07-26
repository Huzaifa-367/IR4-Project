import type { EquipmentByToken } from '@/types/equipment';

/**
 * Extract a permanent equipment qr_token from a raw scan value.
 * Accepts a bare UUID or a public URL ending in `/e/{token}`.
 */
export function parseEquipmentQrToken(raw: string): string | null {
    const trimmed = raw.trim();

    if (trimmed.length === 0) {
        return null;
    }

    const uuidMatch = trimmed.match(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    );

    if (uuidMatch) {
        return uuidMatch[0].toLowerCase();
    }

    try {
        const url = new URL(trimmed);
        const parts = url.pathname.split('/').filter(Boolean);
        const eIndex = parts.findIndex((part) => part === 'e');

        if (eIndex >= 0 && parts[eIndex + 1]) {
            return parseEquipmentQrToken(parts[eIndex + 1]);
        }
    } catch {
        // Not a URL — try trailing path segment.
        const slashParts = trimmed.split('/').filter(Boolean);
        const last = slashParts[slashParts.length - 1];

        if (last) {
            const nested = last.match(
                /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
            );

            if (nested) {
                return nested[0].toLowerCase();
            }
        }
    }

    return null;
}

export async function lookupEquipmentByToken(
    qrToken: string,
): Promise<
    { ok: true; data: EquipmentByToken } | { ok: false; message: string }
> {
    const response = await fetch(
        `/api/equipment/by-token/${encodeURIComponent(qrToken)}`,
        {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        },
    );

    if (response.status === 404) {
        return { ok: false, message: 'No equipment found for that QR token.' };
    }

    if (response.status === 403) {
        return {
            ok: false,
            message: 'You do not have permission to look up equipment.',
        };
    }

    if (!response.ok) {
        return { ok: false, message: `Lookup failed (${response.status}).` };
    }

    const json = (await response.json()) as {
        data: EquipmentByToken;
    };

    return { ok: true, data: json.data };
}
