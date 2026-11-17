import { Head, usePage } from '@inertiajs/react';
import { __ } from '@/lib/i18n';
import type { VetrinaPlan } from '@/vetrina/plans';
import { Vetrina } from '@/vetrina/vetrina';

/** The home page of the platform: the showcase for the businesses that use it. */
export default function Welcome({ plans, featureLabels, demoEnabled }: { plans: VetrinaPlan[]; featureLabels: Record<string, string>; demoEnabled: boolean }) {
    const { name } = usePage().props;

    return (
        <>
            <Head title={__('Online booking software for businesses that work by appointment')}>
                <meta
                    name="description"
                    content={__('Online booking software for businesses that work by appointment: a booking page for your customers, a diary with no overlaps and automatic reminders. Try the calendar and a booking.')}
                />
            </Head>

            <Vetrina name={name} plans={plans} featureLabels={featureLabels} demoEnabled={demoEnabled} />
        </>
    );
}
