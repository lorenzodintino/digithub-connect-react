import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Clock, Globe, MapPin, Phone } from 'lucide-react';
import { PortalLogo } from '@/components/portal-logo';
import { LocationIcon } from '@/components/slot-picker';
import { __ } from '@/lib/i18n';
import type { PortalLayoutProps } from '@/types/portal';

interface Props {
    portal: PortalLayoutProps;
    business: { description: string | null; address: string | null; phone: string | null; website: string | null };
    services: {
        slug: string;
        name: string;
        description: string | null;
        color: string;
        duration: string;
        location: { type: string; label: string };
        price: string | null;
        url: string;
    }[];
}

export default function Show({ portal, business, services }: Props) {
    return (
        <>
            <Head title={portal.name} />

            <main className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
                <div className="space-y-10">
                    <div className="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                        <PortalLogo portal={portal} size="lg" className="shadow-sm" />

                        <div className="min-w-0 space-y-2">
                            <h1 className="text-3xl font-semibold tracking-tight">{portal.name}</h1>

                            {business.description && <p className="max-w-2xl text-muted-foreground">{business.description}</p>}

                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                {business.address && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <MapPin className="size-4" /> {business.address}
                                    </span>
                                )}
                                {business.phone && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Phone className="size-4" /> {business.phone}
                                    </span>
                                )}
                                {business.website && (
                                    <a href={business.website} target="_blank" rel="noopener" className="inline-flex items-center gap-1.5 hover:underline">
                                        <Globe className="size-4" /> {__('Website')}
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">{__('Choose a service')}</h2>

                        {services.length === 0 ? (
                            <div className="rounded-2xl border border-dashed px-6 py-16 text-center">
                                <p className="text-muted-foreground">{__('No service is bookable right now. Please check back later.')}</p>
                            </div>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {services.map((service) => (
                                    <Link
                                        key={service.slug}
                                        href={service.url}
                                        className="group relative flex flex-col gap-3 overflow-hidden rounded-2xl border bg-background p-5 transition hover:-translate-y-0.5 hover:shadow-lg"
                                    >
                                        <span className="absolute inset-x-0 top-0 h-1" style={{ backgroundColor: service.color }} />

                                        <div className="flex items-start justify-between gap-3 pt-1">
                                            <h3 className="text-base font-semibold">{service.name}</h3>
                                            <ArrowRight className="mt-1 size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5" />
                                        </div>

                                        {service.description && <p className="line-clamp-2 text-sm text-muted-foreground">{service.description}</p>}

                                        <div className="mt-auto flex flex-wrap items-center gap-2 pt-1 text-sm text-muted-foreground">
                                            <span className="inline-flex items-center gap-1.5">
                                                <Clock className="size-4" /> {service.duration}
                                            </span>
                                            <span className="inline-flex items-center gap-1.5">
                                                <LocationIcon type={service.location.type} className="size-4" /> {service.location.label}
                                            </span>
                                            {service.price && <span className="font-medium text-foreground">{service.price}</span>}
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </main>
        </>
    );
}
