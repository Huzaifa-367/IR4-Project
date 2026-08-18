import type { ImgHTMLAttributes } from 'react';

const LOGO_SRC = '/images/logo.png';

export default function AppLogoIcon({
    className,
    alt = 'IR4',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src={LOGO_SRC} alt={alt} className={className} {...props} />;
}
