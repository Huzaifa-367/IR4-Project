<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class TechTeamAlertMail extends Mailable
{
    /**
     * @param  array{
     *     category?: string,
     *     summary?: string,
     *     suggested_action?: string,
     *     severity?: string,
     *     details?: array<string, mixed>,
     * }  $context
     */
    public function __construct(
        public readonly string $alertSubject,
        public readonly array $context = [],
        public readonly string $dedupeKey = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[IR4 tech] '.$this->alertSubject,
        );
    }

    public function content(): Content
    {
        $details = $this->context['details'] ?? null;
        if (! is_array($details)) {
            $details = collect($this->context)
                ->reject(fn ($_, string $key): bool => in_array($key, [
                    'category',
                    'summary',
                    'suggested_action',
                    'severity',
                    'details',
                ], true))
                ->all();
        }

        return new Content(
            view: 'mail.tech-team-alert',
            with: [
                'title' => $this->alertSubject,
                'category' => (string) ($this->context['category'] ?? 'Infrastructure'),
                'severity' => (string) ($this->context['severity'] ?? 'Attention required'),
                'summary' => (string) ($this->context['summary'] ?? $this->alertSubject),
                'suggestedAction' => (string) ($this->context['suggested_action'] ?? 'Investigate on the SCC and clear the condition so the alert can resolve.'),
                'details' => $details,
                'dedupeKey' => $this->dedupeKey,
                'appName' => (string) config('app.name', 'IR4'),
                'appUrl' => (string) config('app.url', ''),
                'raisedAt' => now()->timezone((string) config('app.timezone'))->toDateTimeString(),
                'timezone' => (string) config('app.timezone'),
            ],
        );
    }
}
