import { Link, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { PortalLogo } from '@/components/portal-logo';
import { Toaster } from '@/components/toaster';
import { __ } from '@/lib/i18n';
import type { PortalLayoutProps } from '@/types/portal';

/**
 * The shell of the public pages: the business's name and colours instead of
 * ours. With `?embed=1` the page lives inside the business's own website, so
 * the header and the footer step aside.
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
    const { portal, name } = usePage<{ portal: PortalLayoutProps; name: string }>().props;

    // The brand drives the primary colour: buttons, rings and the chosen day.
    const brand = {
        '--portal-brand': portal.brand,
        '--portal-brand-contrast': portal.brand_contrast,
        '--primary': portal.brand,
        '--primary-foreground': portal.brand_contrast,
        '--ring': portal.brand,
    } as CSSProperties;

    return (
        <div className="flex min-h-screen flex-col bg-zinc-50 dark:bg-zinc-950" style={brand}>
            {!portal.embed && (
                <header className="border-b bg-background/80 backdrop-blur">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Link href={portal.home_url} className="flex min-w-0 items-center gap-3">
                            <PortalLogo portal={portal} />
                            <span className="truncate text-base font-semibold">{portal.name}</span>
                        </Link>

                        <LocaleSwitcher side="bottom" align="end" />
                    </div>
                </header>
            )}

            <div className="flex-1">{children}</div>

            {!portal.embed && (
                <footer className="mx-auto w-full max-w-5xl px-4 pb-10 sm:px-6">
                    <div className="flex flex-col items-center justify-between gap-3 border-t pt-6 text-sm text-muted-foreground sm:flex-row">
                        <span>
                            © {portal.year} {portal.name}
                        </span>

                        <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            {portal.privacy_url && (
                                <a href={portal.privacy_url} target="_blank" rel="noopener" className="hover:underline">
                                    {__('Privacy policy')}
                                </a>
                            )}
                            {portal.terms_url && (
                                <a href={portal.terms_url} target="_blank" rel="noopener" className="hover:underline">
                                    {__('Terms of service')}
                                </a>
                            )}
                            <span>{__('Powered by :app', { app: name })}</span>
                        </span>
                    </div>
                </footer>
            )}

            <Toaster />
        </div>
    );
}
