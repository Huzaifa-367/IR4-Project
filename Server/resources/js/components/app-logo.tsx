import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div
                className="flex aspect-square size-8 items-center justify-center rounded-md shadow-[0_0_16px_var(--accent-dim)]"
                style={{
                    background:
                        'linear-gradient(135deg, var(--accent), var(--accent-strong))',
                }}
            >
                <AppLogoIcon className="size-4.5 fill-current text-[var(--bg)]" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-bold tracking-tight text-text">
                    IR4 Command
                </span>
                <span className="truncate text-[10px] font-medium tracking-[0.08em] text-text-faint uppercase">
                    Safety Center
                </span>
            </div>
        </>
    );
}
