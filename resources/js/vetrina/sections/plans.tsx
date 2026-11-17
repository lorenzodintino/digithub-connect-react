import { __, getLocale } from '@/lib/i18n';
import { formatPrice, lowestPrice, type VetrinaPlan } from '../plans';

/** A limit as the card says it: a number, or «unlimited». */
function limit(value: number | null): string {
    return value === null ? __('unlimited') : value.toLocaleString(getLocale());
}

/** A feature as the card says it: yes or no, or a number of places when the plan has a ceiling. */
function feature(plan: VetrinaPlan, key: string, ceiling?: number | null): string {
    if (!plan.features[key]) {
        return __('no');
    }

    return ceiling === undefined || ceiling === null ? __('yes') : __('up to :count', { count: ceiling.toLocaleString(getLocale()) });
}

/**
 * The plans on sale, read from the table of the platform: the same names, prices and limits that the
 * back-office applies, so this section is never out of date.
 */
export function Plans({ plans, featureLabels, onChoose }: { plans: VetrinaPlan[]; featureLabels: Record<string, string>; onChoose: (plan: string) => void }) {
    const lowest = lowestPrice(plans);

    if (plans.length === 0) {
        return null;
    }

    return (
        <section className="section plans-s" id="piani" aria-labelledby="h-piani" data-reveal>
            <div className="wrap">
                <div className="head">
                    <h2 className="h2" id="h-piani">
                        <span className="rise">
                            <span>{lowest ? __('Plans from :price a month', { price: formatPrice(lowest.cents, lowest.currency) }) : __('Plans')}</span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ '--i': '1' }}>
                        {__('Changes with your business: more services, more operators, more bookings and, from the Pro plan, shared resources and email from your own server.')}
                    </p>
                </div>
                <div className="plans">
                    {plans.map((plan) => {
                        const rows: [string, string][] = [
                            [__('Services'), limit(plan.limits.services)],
                            [__('Operators'), limit(plan.limits.operators)],
                            [__('Bookings per month'), limit(plan.limits.bookings)],
                            [featureLabels.resources, feature(plan, 'resources')],
                            [featureLabels.waiting_list, feature(plan, 'waiting_list', plan.limits.waiting_list)],
                            [featureLabels.packages, feature(plan, 'packages', plan.limits.packages)],
                            [featureLabels.group_services, feature(plan, 'group_services')],
                            [featureLabels.calendar_buttons, feature(plan, 'calendar_buttons')],
                            [featureLabels.embed, feature(plan, 'embed')],
                            [featureLabels.custom_smtp, feature(plan, 'custom_smtp')],
                        ];

                        return (
                            <article key={plan.id} className={plan.featured ? 'plan plan--main' : 'plan'} aria-labelledby={`p-${plan.id}`}>
                                <h3 className="plan__name" id={`p-${plan.id}`}>
                                    {plan.name}
                                </h3>
                                {plan.price_cents !== null && (
                                    <p className="plan__price">
                                        <span>{formatPrice(plan.price_cents, plan.currency)}</span>
                                        <small>{__('a month')}</small>
                                    </p>
                                )}
                                <ul>
                                    {rows.map(([label, value]) => (
                                        <li key={label}>
                                            <span>{label}</span>
                                            <span>{value}</span>
                                        </li>
                                    ))}
                                </ul>
                                <a className={plan.featured ? 'btn btn--primary' : 'btn btn--ghost'} href="#demo" onClick={() => onChoose(plan.name)}>
                                    {__('Choose :plan', { plan: plan.name })}
                                </a>
                            </article>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
