import { Head } from '@inertiajs/react';
import { Info, Layers } from 'lucide-react';
import { ColorBadge } from '@/components/color-badge';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __ } from '@/lib/i18n';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    price: string | null;
    services: string;
    operators: string;
    bookings: string;
    waiting_list: string;
    packages: string;
    included: string[];
    is_active: boolean;
    portals_count: number;
}

/**
 * The plans as the License Manager defines them: this page only shows them. Plans, prices and what each one
 * includes are changed in the License Manager and nowhere else.
 */
export default function Plans({ plans, featureLabels, syncedAt }: { plans: Plan[]; featureLabels: Record<string, string>; syncedAt: string | null }) {
    return (
        <>
            <Head title={__('Plans')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Plans')} subheading={__('What each portal is entitled to.')} />

                <Alert data-test="plans-managed">
                    <Info />
                    <AlertDescription>
                        {syncedAt
                            ? __('The plans are defined in the License Manager and copied here: last aligned on :date. Change them there.', { date: syncedAt })
                            : __('The plans are defined in the License Manager and copied here: not aligned yet. Change them there.')}
                    </AlertDescription>
                </Alert>

                <Alert>
                    <Info />
                    <AlertDescription>
                        {__(
                            'Services and operators past the limit are refused. The monthly bookings are a threshold: past it customers still book, and the manager is warned. A portal without its own SMTP in the plan sends from the platform. A portal without resources in the plan keeps the ones it has but cannot add or change any.',
                        )}
                    </AlertDescription>
                </Alert>

                {plans.length === 0 ? (
                    <EmptyState icon={Layers} title={__('No plan yet')}>
                        <p>{__('The plans appear here once they are read from the License Manager.')}</p>
                    </EmptyState>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-4">{__('Plan')}</TableHead>
                                    <TableHead>{__('Price')}</TableHead>
                                    <TableHead>{__('Services')}</TableHead>
                                    <TableHead>{__('Operators')}</TableHead>
                                    <TableHead>{__('Bookings / month')}</TableHead>
                                    <TableHead>{__('Waiting list')}</TableHead>
                                    <TableHead>{__('Packages')}</TableHead>
                                    <TableHead>{__('Includes')}</TableHead>
                                    <TableHead className="pe-4">{__('Portals')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {plans.map((plan) => (
                                    <TableRow key={plan.id}>
                                        <TableCell className="ps-4 whitespace-normal">
                                            <div className="font-medium">{plan.name}</div>
                                            {plan.description && <div className="text-sm text-muted-foreground">{plan.description}</div>}
                                            {!plan.is_active && (
                                                <ColorBadge color="zinc" className="mt-1">
                                                    {__('Retired')}
                                                </ColorBadge>
                                            )}
                                        </TableCell>
                                        <TableCell className="tabular-nums">{plan.price ?? __('Free')}</TableCell>
                                        <TableCell className="tabular-nums">{plan.services}</TableCell>
                                        <TableCell className="tabular-nums">{plan.operators}</TableCell>
                                        <TableCell className="tabular-nums">{plan.bookings}</TableCell>
                                        <TableCell className="tabular-nums">{plan.included.includes(featureLabels.waiting_list) ? plan.waiting_list : '—'}</TableCell>
                                        <TableCell className="tabular-nums">{plan.included.includes(featureLabels.packages) ? plan.packages : '—'}</TableCell>
                                        <TableCell className="max-w-64 whitespace-normal text-sm text-muted-foreground">{plan.included.join(', ') || '—'}</TableCell>
                                        <TableCell className="pe-4 tabular-nums">{plan.portals_count}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}
