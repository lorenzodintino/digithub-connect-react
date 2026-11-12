import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, Building2, CalendarDays, Clock, ExternalLink, IdCard, LayoutGrid, RefreshCw, UsersRound } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { CopyableInput } from '@/components/copyable-input';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import * as adminRoutes from '@/routes/admin';
import { recheck } from '@/routes/license';
import { show as showPortal } from '@/routes/admin/portals';
import * as appRoutes from '@/routes/app';

interface AdminData {
    stats: { portals: number; active_portals: number; managers: number; operators: number };
    recent_portals: { id: number; slug: string; name: string; manager_email: string | null; services_count: number; is_active: boolean }[];
}

interface LicenseCardData {
    state: string;
    color: string;
    plan: string | null;
    starts_at: string | null;
    expires_at: string | null;
    lifetime: boolean;
    grace_ends_at: string | null;
    in_grace: boolean;
    checked_at: string | null;
    stale: boolean;
    amounts: { due: string | null; paid: string | null; outstanding: string | null; outstanding_label: string; owes: boolean } | null;
    renewal: { period: string; price: string }[];
    tenant_id: string;
}

interface ManagerData {
    stats: { services: number; staff: number; upcoming: number; pending: number };
    /** Only while the License Manager governs the portal. */
    license: LicenseCardData | null;
}

interface OperatorData {
    next_bookings: { id: number; customer: string; service: string; color: string; day: string; time: string }[];
    week: { id: number; day: string; hours: string }[];
    services_count: number;
    upcoming_count: number;
}

/** DashboardController sends exactly one of the three, according to the role. */
interface Props {
    admin?: AdminData;
    manager?: ManagerData;
    operator?: OperatorData;
}

function Panel({ heading, action, children, className }: { heading: string; action?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={cn('rounded-xl border', className)}>
            <div className="flex items-center justify-between gap-4 border-b px-5 py-4">
                <h2 className="text-lg font-semibold">{heading}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}

function EmptyState({ children }: { children: ReactNode }) {
    return <p className="px-5 py-10 text-center text-muted-foreground">{children}</p>;
}

function SeeMore({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Button size="sm" variant="ghost" asChild>
            <Link href={href}>
                {children}
                <ArrowRight />
            </Link>
        </Button>
    );
}

function AdminDashboard({ data }: { data: AdminData }) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Building2} label={__('Portals')} value={data.stats.portals} />
                <StatCard icon={BadgeCheck} label={__('Active portals')} value={data.stats.active_portals} />
                <StatCard icon={IdCard} label={__('Managers')} value={data.stats.managers} />
                <StatCard icon={UsersRound} label={__('Operators')} value={data.stats.operators} />
            </div>

            <Panel heading={__('Latest portals')} action={<SeeMore href={adminRoutes.portals.url()}>{__('Manage')}</SeeMore>}>
                {data.recent_portals.length === 0 ? (
                    <EmptyState>{__('No portal has been created yet.')}</EmptyState>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="ps-5">{__('Portal')}</TableHead>
                                <TableHead>{__('Manager')}</TableHead>
                                <TableHead>{__('Services')}</TableHead>
                                <TableHead className="pe-5">{__('Status')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.recent_portals.map((portal) => (
                                <TableRow key={portal.id}>
                                    <TableCell className="ps-5">
                                        <Link href={showPortal(portal.slug)} className="font-medium hover:underline">
                                            {portal.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{portal.manager_email ?? __('Not assigned')}</TableCell>
                                    <TableCell className="tabular-nums">{portal.services_count}</TableCell>
                                    <TableCell className="pe-5">
                                        <Badge
                                            variant="secondary"
                                            className={cn(portal.is_active && 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200')}
                                        >
                                            {portal.is_active ? __('Active') : __('Suspended')}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>
        </>
    );
}

/** The licence of the portal as the manager reads it: verdict, dates, what is due and paid, what renewing costs. */
function LicenseCard({ license }: { license: LicenseCardData }) {
    const [updating, setUpdating] = useState(false);

    function update(): void {
        router.post(recheck.url(), {}, { preserveScroll: true, onStart: () => setUpdating(true), onFinish: () => setUpdating(false) });
    }

    const detail = (label: string, value: ReactNode) => (
        <div className="min-w-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );

    return (
        <section className="rounded-xl border p-5" aria-labelledby="license-card-title">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="license-card-title" className="text-lg font-semibold">
                        {__('License')}
                    </h2>
                    <p className="mt-1 flex items-center gap-2 text-muted-foreground">
                        {license.plan ?? __('No plan')}
                        <ColorBadge color={license.color}>{license.state}</ColorBadge>
                    </p>
                </div>
                <Button type="button" size="sm" variant="secondary" onClick={update} disabled={updating}>
                    <RefreshCw className={updating ? 'animate-spin' : undefined} />
                    {updating ? __('Updating…') : __('Update')}
                </Button>
            </div>

            <dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {detail(__('Starts on'), license.starts_at ?? '—')}
                {detail(__('Ends on'), license.lifetime ? __('Never') : (license.expires_at ?? '—'))}
                {license.grace_ends_at && detail(license.in_grace ? __('Renew by') : __('Tolerance ends on'), license.grace_ends_at)}
                {detail(__('Last update'), license.checked_at ?? '—')}
            </dl>

            {license.amounts && (
                <dl className="mt-4 grid gap-4 border-t pt-4 sm:grid-cols-3">
                    {detail(__('Total due'), license.amounts.due ?? '—')}
                    {detail(__('Already paid'), license.amounts.paid ?? '—')}
                    {detail(license.amounts.outstanding_label, <span className={license.amounts.owes ? 'text-amber-600 dark:text-amber-400' : undefined}>{license.amounts.outstanding ?? '—'}</span>)}
                </dl>
            )}

            {license.renewal.length > 0 && (
                <div className="mt-4 border-t pt-4">
                    <h3 className="text-sm text-muted-foreground">{__('Renewal price')}</h3>
                    <ul className="mt-2 flex flex-wrap gap-2">
                        {license.renewal.map((price) => (
                            <li key={price.period} className="rounded-lg border px-3 py-1.5 text-sm">
                                <span className="text-muted-foreground">{price.period}</span> <span className="font-medium tabular-nums">{price.price}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {license.stale && <p className="mt-4 text-sm text-amber-700 dark:text-amber-300">{__('The License Manager did not answer: the last known data is shown.')}</p>}

            <div className="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">
                <div className="min-w-0 flex-1 basis-64">
                    <label htmlFor="license-tenant-id" className="text-sm font-medium">
                        {__('Portal id')}
                    </label>
                    <p className="mb-2 text-sm text-muted-foreground">{__('Give this id to the platform administrator: it is what the License Manager knows your portal by.')}</p>
                    <CopyableInput id="license-tenant-id" value={license.tenant_id} className="max-w-sm [&_input]:font-mono [&_input]:text-sm [&_input]:font-semibold" />
                </div>
            </div>
        </section>
    );
}

function ManagerDashboard({ data, publicUrl }: { data: ManagerData; publicUrl: string }) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={CalendarDays} label={__('Upcoming bookings')} value={data.stats.upcoming} />
                <StatCard
                    icon={Clock}
                    label={__('Waiting for approval')}
                    value={data.stats.pending}
                    hint={data.stats.pending > 0 ? __('Review them in Bookings') : null}
                />
                <StatCard icon={LayoutGrid} label={__('Published services')} value={data.stats.services} />
                <StatCard icon={UsersRound} label={__('Bookable people')} value={data.stats.staff} />
            </div>

            {data.license && <LicenseCard license={data.license} />}

            <div className="grid gap-4 lg:grid-cols-2">
                <section className="rounded-xl border p-5">
                    <h2 className="text-lg font-semibold">{__('Get started')}</h2>
                    <p className="mt-1 text-muted-foreground">{__('Three steps and your portal is ready to take bookings.')}</p>

                    <div className="mt-4 space-y-3">
                        <Button variant="secondary" className="w-full justify-start" asChild>
                            <Link href={appRoutes.services()}>
                                <LayoutGrid />
                                {__('Create your services')}
                            </Link>
                        </Button>
                        <Button variant="secondary" className="w-full justify-start" asChild>
                            <Link href={appRoutes.staff()}>
                                <UsersRound />
                                {__('Add your operators')}
                            </Link>
                        </Button>
                        <Button variant="secondary" className="w-full justify-start" asChild>
                            <Link href={appRoutes.availability()}>
                                <Clock />
                                {__('Set weekly availability')}
                            </Link>
                        </Button>
                    </div>
                </section>

                <section className="rounded-xl border p-5">
                    <h2 className="text-lg font-semibold">{__('Share your portal')}</h2>
                    <p className="mt-1 text-muted-foreground">{__('Send this link to your customers or embed it in your website.')}</p>

                    <CopyableInput className="mt-4" value={publicUrl} />

                    <Button className="mt-3" asChild>
                        <a href={publicUrl} target="_blank" rel="noopener">
                            {__('Open public page')}
                            <ExternalLink />
                        </a>
                    </Button>
                </section>
            </div>
        </>
    );
}

function OperatorDashboard({ data }: { data: OperatorData }) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard icon={CalendarDays} label={__('Upcoming bookings')} value={data.upcoming_count} />
                <StatCard icon={Clock} label={__('Weekly slots')} value={data.week.length} />
                <StatCard icon={LayoutGrid} label={__('My services')} value={data.services_count} />
            </div>

            <Panel heading={__('Your next bookings')} action={<SeeMore href={appRoutes.bookings.url()}>{__('See all')}</SeeMore>}>
                {data.next_bookings.length === 0 ? (
                    <EmptyState>{__('Nothing booked yet.')}</EmptyState>
                ) : (
                    <div className="divide-y">
                        {data.next_bookings.map((booking) => (
                            <div key={booking.id} className="flex items-center justify-between gap-4 px-5 py-3">
                                <div className="flex items-center gap-3">
                                    <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: booking.color }} />
                                    <div>
                                        <div className="font-medium">{booking.customer}</div>
                                        <div className="text-sm text-muted-foreground">{booking.service}</div>
                                    </div>
                                </div>

                                <div className="text-end">
                                    <div className="font-medium capitalize">{booking.day}</div>
                                    <div className="text-sm text-muted-foreground tabular-nums">{booking.time}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Panel>

            <section className="rounded-xl border p-5">
                <h2 className="text-lg font-semibold">{__('Your week')}</h2>

                {data.week.length === 0 ? (
                    <>
                        <p className="mt-2 text-muted-foreground">{__('You have not set any working hours yet.')}</p>
                        <Button className="mt-4" asChild>
                            <Link href={appRoutes.availability()}>
                                <Clock />
                                {__('Set weekly availability')}
                            </Link>
                        </Button>
                    </>
                ) : (
                    <div className="mt-4 space-y-2">
                        {data.week.map((rule) => (
                            <div key={rule.id} className="flex items-center justify-between rounded-lg bg-muted px-4 py-2">
                                <span className="font-medium">{rule.day}</span>
                                <span className="tabular-nums text-muted-foreground">{rule.hours}</span>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}

export default function Dashboard({ admin, manager, operator }: Props) {
    const { auth } = usePage().props;
    const user = auth.user!;

    return (
        <>
            <Head title={__('Dashboard')} />

            <div className="flex w-full flex-col gap-8">
                <PageHeader heading={__('Hi :name', { name: user.name })} subheading={user.tenant?.name ?? __('Platform administration')} />

                {admin && <AdminDashboard data={admin} />}
                {manager && user.tenant && <ManagerDashboard data={manager} publicUrl={user.tenant.public_url} />}
                {operator && <OperatorDashboard data={operator} />}
            </div>
        </>
    );
}
