import { cn } from '@/lib/utils';
import type { PortalLayoutProps } from '@/types/portal';

/** The logo of the business, or its initials on its brand colour. */
export function PortalLogo({ portal, size = 'md', className }: { portal: PortalLayoutProps; size?: 'md' | 'lg'; className?: string }) {
    const classes = size === 'lg' ? 'size-16 rounded-2xl text-xl' : 'size-9 rounded-xl text-sm';

    if (portal.logo) {
        return <img src={portal.logo} alt={portal.name} className={cn('shrink-0 object-cover', classes, className)} />;
    }

    return (
        <span
            className={cn('flex shrink-0 items-center justify-center font-semibold', classes, className)}
            style={{ backgroundColor: portal.brand, color: portal.brand_contrast }}
        >
            {portal.initials}
        </span>
    );
}
