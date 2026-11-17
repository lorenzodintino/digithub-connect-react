import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppLogoMark } from '@/components/app-logo';
import { Toaster } from '@/components/toaster';
import { home } from '@/routes';

/** The sign-in pages: the logo, one narrow column, nothing else. */
export default function AuthLayout({ children }: { children: ReactNode }) {
    const { name } = usePage<{ name: string }>().props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="flex w-full max-w-sm flex-col gap-2">
                <Link href={home.url()} className="flex flex-col items-center gap-2 font-medium">
                    <AppLogoMark className="mb-1 size-12 rounded-xl" iconClassName="size-10" />
                    <span className="sr-only">{name}</span>
                </Link>

                <div className="flex flex-col gap-6 rounded-2xl border bg-card p-6 sm:p-8">{children}</div>
            </div>

            <Toaster />
        </div>
    );
}
