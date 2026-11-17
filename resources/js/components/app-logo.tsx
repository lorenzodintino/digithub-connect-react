import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * The Atrio symbol: a «A» of two slabs and a bar whose right foot is a dot, the
 * guest. The A takes the current colour; the dot keeps the brand cobalt whatever
 * `--primary` is (public pages swap it for the business's own colour).
 */
export function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" aria-hidden="true" {...props}>
            <path fill="currentColor" fillRule="evenodd" d="M9 94L40 6L60 6L80.4 64L39.6 64L29 94ZM50 34.4L43.1 54L56.9 54L50 34.4Z" />
            <path fill="#3a5cff" d="M89.4 81C89.4 88.18 83.58 94 76.4 94C69.22 94 63.4 88.18 63.4 81C63.4 73.82 69.22 68 76.4 68C83.58 68 89.4 73.82 89.4 81Z" />
        </svg>
    );
}

/** The app icon: a graphite tile with the symbol, the same in light and dark. */
export function AppLogoMark({ className, iconClassName }: { className?: string; iconClassName?: string }) {
    return (
        <span className={cn('flex aspect-square size-8 items-center justify-center rounded-lg bg-[#191c22] text-[#f5f7fa] ring-1 ring-black/5 dark:ring-white/10', className)}>
            <AppLogoIcon className={cn('size-7', iconClassName)} />
        </span>
    );
}

export function AppLogo({ name }: { name: string }) {
    return (
        <>
            <AppLogoMark />
            <span className="truncate font-extrabold tracking-tight">{name}</span>
        </>
    );
}
