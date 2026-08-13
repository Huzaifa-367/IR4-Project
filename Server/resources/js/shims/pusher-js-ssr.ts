/**
 * Vite SSR stub for pusher-js.
 *
 * The real package's Node build uses `require()` and breaks under Vite's ESM
 * SSR runner ("require is not defined" / "Pusher is not a constructor").
 * Echo must not open sockets during SSR — app.tsx uses the null broadcaster.
 */
export default class Pusher {
    constructor(..._args: unknown[]) {
        void _args;
    }

    connect(): void {}

    disconnect(): void {}

    bind(..._args: unknown[]): this {
        void _args;

        return this;
    }

    unbind(..._args: unknown[]): this {
        void _args;

        return this;
    }

    subscribe(..._args: unknown[]): {
        bind: () => void;
        unbind: () => void;
    } {
        void _args;

        return {
            bind: (): void => undefined,
            unbind: (): void => undefined,
        };
    }

    unsubscribe(..._args: unknown[]): void {
        void _args;
    }
}
