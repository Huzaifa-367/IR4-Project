import { describe, expect, it } from 'vitest';
import { buildReverbEchoOptions } from '@/lib/reverb-client';

describe('buildReverbEchoOptions', () => {
    it('uses the LAN HTTP origin the operator is already browsing', () => {
        const actual = buildReverbEchoOptions('app-key', {
            hostname: '192.168.8.40',
            port: '9100',
            protocol: 'http:',
        });

        expect(actual).toEqual({
            broadcaster: 'reverb',
            key: 'app-key',
            wsHost: '192.168.8.40',
            wsPort: 9100,
            wssPort: 9100,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
        });
    });

    it('uses HTTPS defaults when the page is on 443 with no explicit port', () => {
        const actual = buildReverbEchoOptions('app-key', {
            hostname: 'ir4-project.test',
            port: '',
            protocol: 'https:',
        });

        expect(actual.wsHost).toBe('ir4-project.test');
        expect(actual.wsPort).toBe(443);
        expect(actual.wssPort).toBe(443);
        expect(actual.forceTLS).toBe(true);
    });
});
