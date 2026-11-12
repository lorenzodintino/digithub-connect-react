import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Globe, LayoutGrid, UsersRound } from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';
import { dashboard, login } from '@/routes';

/** The home page of the platform, for the businesses that use it. */
export default function Welcome() {
    const { name, auth } = usePage().props;

    const features = [
        { icon: LayoutGrid, title: __('Your services'), text: __('Duration, buffers, price and booking rules for everything you offer.') },
        { icon: UsersRound, title: __('Your team'), text: __('Each operator has their own login, profile and weekly availability.') },
        { icon: Globe, title: __('Your public page'), text: __('One link to share, or an iframe to embed in your own website.') },
    ];

    return (
        <>
            <Head title={__('Bookings made simple')} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6">
                    <div className="flex items-center gap-2">
                        <AppLogo name={name} />
                    </div>

                    <div className="flex items-center gap-2">
                        <LocaleSwitcher side="bottom" align="end" />

                        <Button size="sm" asChild>
                            {auth.user ? <Link href={dashboard.url()}>{__('Dashboard')}</Link> : <Link href={login.url()}>{__('Log in')}</Link>}
                        </Button>
                    </div>
                </header>

                <main>
                    <section className="mx-auto max-w-6xl px-6 py-16 sm:py-24">
                        <div className="max-w-3xl space-y-6">
                            <span className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm text-muted-foreground">
                                <span className="size-1.5 rounded-full bg-emerald-500" />
                                {__('Booking platform for businesses')}
                            </span>

                            <h1 className="text-4xl font-semibold tracking-tight sm:text-6xl">{__('Bookings made simple')}</h1>

                            <p className="max-w-2xl text-lg text-muted-foreground">
                                {__('Give every business its own booking portal: services, staff, opening hours and a public page ready to share by link or embed in any website.')}
                            </p>

                            <div className="flex flex-wrap gap-3 pt-2">
                                <Button asChild>
                                    <Link href={login.url()}>
                                        {__('Access your portal')}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto max-w-6xl px-6 pb-24">
                        <div className="grid gap-4 sm:grid-cols-3">
                            {features.map((feature) => (
                                <div key={feature.title} className="rounded-2xl border p-6">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-muted">
                                        <feature.icon className="size-5" />
                                    </span>
                                    <h2 className="mt-4 font-semibold">{feature.title}</h2>
                                    <p className="mt-1 text-sm text-muted-foreground">{feature.text}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="mx-auto max-w-6xl px-6 pb-10">
                    <div className="border-t pt-6 text-sm text-muted-foreground">
                        © {new Date().getFullYear()} {name}
                    </div>
                </footer>
            </div>
        </>
    );
}
