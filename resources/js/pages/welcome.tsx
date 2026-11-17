import { Head, usePage } from '@inertiajs/react';
import { __ } from '@/lib/i18n';
import type { VetrinaPlan } from '@/vetrina/plans';
import { Vetrina } from '@/vetrina/vetrina';

/** The home page of the platform: the showcase for the businesses that use it. */
export default function Welcome({ plans, featureLabels }: { plans: VetrinaPlan[]; featureLabels: Record<string, string> }) {
    const { name } = usePage().props;

    return (
        <>
            <Head title={__('Booking software for studios, centres and salons')}>
                <meta
                    name="description"
                    content={__('Online booking software for studios, centres and salons: a portal with your brand, a diary with no overlaps, nine emails in Italian and English. Try the calendar and a booking.')}
                />
            </Head>

            <Vetrina name={name} plans={plans} featureLabels={featureLabels} />
        </>
    );
}
