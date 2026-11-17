import { getLocale } from '@/lib/i18n';

/** A plan on sale, as `App\Support\PlanCatalogue` sends it to the home page. */
export interface VetrinaPlan {
    id: number;
    code: string | null;
    name: string;
    price_cents: number | null;
    currency: string;
    featured: boolean;
    limits: { services: number | null; operators: number | null; bookings: number | null; waiting_list: number | null; packages: number | null };
    features: Record<string, boolean>;
}

/** «19 €» or «€19.50»: whole amounts without decimals, in the language of the page. */
export function formatPrice(cents: number, currency: string): string {
    const whole = cents % 100 === 0;

    return new Intl.NumberFormat(getLocale(), { style: 'currency', currency, minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 }).format(cents / 100);
}

/** The lowest monthly price of the plans on sale, or null when none carries one. */
export function lowestPrice(plans: VetrinaPlan[]): { cents: number; currency: string } | null {
    const priced = plans.filter((plan) => plan.price_cents !== null);

    if (priced.length === 0) {
        return null;
    }

    const cheapest = priced.reduce((best, plan) => ((plan.price_cents as number) < (best.price_cents as number) ? plan : best));

    return { cents: cheapest.price_cents as number, currency: cheapest.currency };
}
