import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    iconClassName?: string;
};

export default function AppLogo({ className, iconClassName }: Props) {
    return (
        <div
            className={cn(
                'flex items-center justify-center overflow-hidden',
                className,
            )}
        >
            <AppLogoIcon
                className={cn(
                    'h-10 w-auto max-w-[180px] object-contain',
                    iconClassName,
                )}
            />
        </div>
    );
}
