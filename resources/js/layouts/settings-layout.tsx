import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Separator } from '@/components/ui/separator';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

/** The three account screens side by side, under one heading. */
export default function SettingsLayout({ heading, subheading, children }: { heading: string; subheading: string; children: ReactNode }) {
    const currentPath = usePage().url.split('?')[0];

    const items = [
        { label: __('Profile'), href: editProfile.url() },
        { label: __('Security'), href: editSecurity.url() },
        { label: __('Appearance'), href: editAppearance.url() },
    ];

    return (
        <div className="w-full">
            <PageHeader heading={__('Settings')} subheading={__('Manage your profile and account settings')} />
            <Separator className="my-6" />

            <div className="flex items-start max-md:flex-col">
                <nav aria-label={__('Settings')} className="me-10 w-full pb-4 md:w-[220px]">
                    <ul className="flex flex-col gap-1">
                        {items.map((item) => (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    className={cn(
                                        'block rounded-lg px-3 py-2 text-sm font-semibold text-muted-foreground transition hover:bg-accent hover:text-foreground',
                                        currentPath === item.href && 'bg-accent text-foreground',
                                    )}
                                    aria-current={currentPath === item.href ? 'page' : undefined}
                                >
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <Separator className="md:hidden" />

                <div className="flex-1 self-stretch max-md:pt-6">
                    <h2 className="font-bold tracking-tight">{heading}</h2>
                    <p className="text-sm text-muted-foreground">{subheading}</p>

                    <div className="mt-5 w-full max-w-lg">{children}</div>
                </div>
            </div>
        </div>
    );
}
