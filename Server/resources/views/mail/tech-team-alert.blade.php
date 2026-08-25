<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#18181b;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e4e4e7;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#18181b;padding:16px 24px;">
                            <p style="margin:0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#a1a1aa;">
                                {{ $appName }} · tech-team alert
                            </p>
                            <p style="margin:6px 0 0;font-size:13px;color:#fafafa;">
                                {{ $category }} · {{ $severity }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.25;color:#18181b;">
                                {{ $title }}
                            </h1>
                            <p style="margin:0 0 20px;font-size:15px;color:#3f3f46;">
                                {{ $summary }}
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;border:1px solid #e4e4e7;border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 14px;background:#fafafa;border-bottom:1px solid #e4e4e7;font-size:12px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#71717a;">
                                        Details
                                    </td>
                                </tr>
                                @forelse ($details as $label => $value)
                                    <tr>
                                        <td style="padding:10px 14px;border-bottom:1px solid #f4f4f5;font-size:14px;">
                                            <span style="display:inline-block;min-width:140px;color:#71717a;">{{ is_string($label) ? str_replace('_', ' ', $label) : $label }}</span>
                                            <span style="color:#18181b;font-weight:500;">
                                                @if (is_bool($value))
                                                    {{ $value ? 'yes' : 'no' }}
                                                @elseif (is_array($value))
                                                    {{ implode('; ', array_map(static fn ($item) => is_scalar($item) ? (string) $item : json_encode($item), $value)) }}
                                                @elseif ($value === null || $value === '')
                                                    —
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td style="padding:10px 14px;font-size:14px;color:#71717a;">No extra detail was attached.</td>
                                    </tr>
                                @endforelse
                                <tr>
                                    <td style="padding:10px 14px;font-size:14px;">
                                        <span style="display:inline-block;min-width:140px;color:#71717a;">Raised</span>
                                        <span style="color:#18181b;font-weight:500;">{{ $raisedAt }} ({{ $timezone }})</span>
                                    </td>
                                </tr>
                                @if ($dedupeKey !== '')
                                    <tr>
                                        <td style="padding:10px 14px;font-size:13px;border-top:1px solid #f4f4f5;">
                                            <span style="display:inline-block;min-width:140px;color:#71717a;">Reference</span>
                                            <span style="color:#52525b;font-family:ui-monospace,Menlo,monospace;font-size:12px;">{{ $dedupeKey }}</span>
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;">
                                <tr>
                                    <td style="padding:14px;">
                                        <p style="margin:0 0 6px;font-size:12px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#9a3412;">
                                            Suggested action
                                        </p>
                                        <p style="margin:0;font-size:14px;color:#7c2d12;">
                                            {{ $suggestedAction }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            @if ($appUrl !== '')
                                <p style="margin:0 0 8px;font-size:14px;">
                                    <a href="{{ $appUrl }}" style="color:#1d4ed8;text-decoration:none;">Open SCC → {{ $appUrl }}</a>
                                </p>
                            @endif

                            <p style="margin:16px 0 0;font-size:12px;color:#71717a;">
                                This message is for the tech team only. Operator safety alerts (fall, PPE, gas alarms, zones) stay in the SCC Alert Centre and are not mailed here.
                                You will not get another mail for the same condition until it clears and returns.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
