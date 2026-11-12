import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppLogoIcon } from '@/components/app-logo';
import { Toaster } from '@/components/toaster';
import { home } from '@/routes';

/** The sign-in pages: the logo, one narrow column, nothing else. */
export default function AuthLayout({ children }: { children: ReactNode }) {
    const { name } = usePage<{ name: string }>().props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="flex w-full max-w-sm flex-col gap-2">
                <Link href={home.url()} className="flex flex-col items-center gap-2 font-medium">
                    <span className="mb-1 flex size-9 items-center justify-center rounded-md">
                        <AppLogoIcon className="size-9 text-foreground" />
                    </span>
                    <span className="sr-only">{name}</span>
                </Link>

                <div className="flex flex-col gap-6">{children}</div>
            </div>

            <Toaster />
        </div>
    );
}
