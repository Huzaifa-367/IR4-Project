/**
 * Vite SSR stub for pusher-js.
 *
 * The real package's Node build uses `require()` and breaks under Vite's ESM
 * SSR runner ("require is not defined" / "Pusher is not a constructor").
 * Echo must not open sockets during SSR — app.tsx uses the null broadcaster.
 */
export default class Pusher {
    constructor(_key?: string, _options?: unknown) {}

    connect(): void {}

    disconnect(): void {}

    bind(_event?: string, _callback?: (...args: unknown[]) => void): this {
        return this;
    }

    unbind(_event?: string, _callback?: (...args: unknown[]) => void): this {
        return this;
    }

    subscribe(_channel?: string): {
        bind: () => void;
        unbind: () => void;
    } {
        return {
            bind: (): void => undefined,
            unbind: (): void => undefined,
        };
    }

    unsubscribe(_channel?: string): void {}
}
