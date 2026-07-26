import type { ReactNode } from 'react';
import {
    SectionInfo
    
} from '@/components/ir4/section-info';
import type {SectionInfoContent} from '@/components/ir4/section-info';
import { CardDescription, CardTitle } from '@/components/ui/card';

type Props = {
    title: string;
    info: SectionInfoContent;
    description?: ReactNode;
    className?: string;
    children?: ReactNode;
};

/** Card title row with the shared (i) data explainer. */
export function CardHeading({
    title,
    info,
    description,
    className,
    children,
}: Props) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex min-w-0 flex-col gap-1">
                <div className="flex items-center gap-2">
                    <CardTitle className={className ?? 'text-sm'}>
                        {title}
                    </CardTitle>
                    <SectionInfo info={info} />
                </div>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </div>
            {children}
        </div>
    );
}
