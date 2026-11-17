import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronRight, Clock, ExternalLink, Globe, KeyRound, Layers, LogIn, PauseCircle, PlayCircle, SquareStack, UsersRound } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { CopyButton } from '@/components/copy-button';
import { CopyableInput } from '@/components/copyable-input';
import { CredentialsDialog } from '@/components/credentials-dialog';
import { Switch } from '@/components/ui/switch';
import { FormField } from '@/components/form-field';
import { type MailSettings, MailSettingsPanel } from '@/components/mail-settings';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __ } from '@/lib/i18n';
import { plans as plansRoute, portals as portalsRoute } from '@/routes/admin';
import * as portalRoutes from '@/routes/admin/portals';
import * as licenseRoutes from '@/routes/admin/portals/license';
import * as mailRoutes from '@/routes/admin/portals/mail';
import * as userRoutes from '@/routes/admin/users';

interface Person {
    id: number;
    name: string;
    email: string;
    initials: string;
    avatar: string | null;
}

interface Props {
    portal: {
        slug: string;
        name: string;
        description: string | null;
        is_active: boolean;
        public_url: string;
        timezone: string;
        contact: { email: string | null; phone: string | null; website: string | null; address: string | null };
    };
    manager: Person | null;
    impersonationMinutes: number;
    billing: {
        tenant_id: string;
        complete: boolean;
        gaps: string[];
        kind: string | null;
        values: Record<string, string>;
        text: string;
    };
    license: {
        enforcement: 'off' | 'observe' | 'on';
        configuration_problem: string | null;
        exempt: boolean;
        governed: boolean;
        state: string;
        color: string;
        expires_at: string | null;
        lifetime: boolean;
        grace_ends_at: string | null;
        plan: string | null;
        local_plan: string | null;
        plan_unknown: boolean;
        checked_at: string | null;
        stale: boolean;
        alarm: boolean;
        error: string | null;
        balance: string | null;
        would_block: string | null;
    };
    plan: {
        name: string;
        from_license: boolean;
        price: string | null;
        services: string;
        operators: string;
        bookings: string;
        bookings_threshold: 'reached' | 'near' | null;
        own_smtp: boolean;
    } | null;
    subscription: { governed: boolean; paid_until: string; label: string; color: string; suspended_for_payment: boolean; suspends_on: string | null };
    mail: MailSettings;
    services: { id: number; name: string; color: string; duration: string; operators_count: number; is_active: boolean }[];
    operators: (Person & { weekly_slots: number; is_active: boolean })[];
}

function PersonAvatar({ person, className }: { person: Person; className?: string }) {
    return (
        <Avatar className={className}>
            {person.avatar && <AvatarImage src={person.avatar} alt={person.name} />}
            <AvatarFallback>{person.initials}</AvatarFallback>
        </Avatar>
    );
}

function Section({ title, actions, children, className }: { title: string; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={`rounded-xl border p-5 ${className ?? ''}`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">{title}</h2>
                {actions}
            </div>
            {children}
        </section>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="truncate font-medium tabular-nums">{children}</dd>
        </div>
    );
}

function PaidUntilForm({ portal, subscription }: Pick<Props, 'portal' | 'subscription'>) {
    const form = useForm({ paid_until: subscription.paid_until });

    function save(event: FormEvent): void {
        event.preventDefault();
        form.put(portalRoutes.paidUntil.url(portal.slug), { preserveScroll: true });
    }

    return (
        <form onSubmit={save} className="mt-5 flex flex-wrap items-end gap-3 border-t pt-5">
            <FormField
                id="paid-until"
                label={__('Paid until')}
                description={__('Last day covered by the payment. Leave empty for a portal that never expires.')}
                error={form.errors.paid_until}
                className="max-w-xs"
            >
                <Input id="paid-until" type="date" value={form.data.paid_until} onChange={(event) => form.setData('paid_until', event.target.value)} />
            </FormField>

            <Button type="submit" variant="secondary" disabled={form.processing}>
                {__('Save')}
            </Button>

            <div className="flex flex-wrap items-center gap-2 pb-2">
                <ColorBadge color={subscription.color}>{subscription.label}</ColorBadge>

                {subscription.suspended_for_payment ? (
                    <span className="text-sm text-muted-foreground">{__('Suspended for non-payment. Saving a date that covers today brings it back.')}</span>
                ) : (
                    subscription.suspends_on && (
                        <span className="text-sm text-muted-foreground">{__('Suspended automatically on :date if not renewed.', { date: subscription.suspends_on })}</span>
                    )
                )}
            </div>
        </form>
    );
}

/** Who pays for the portal, what is missing to invoice it, and what to copy into the License Manager. */
function BillingSection({ billing }: { billing: Props['billing'] }) {
    const values = billing.values;
    const legalName = billing.kind === null ? '' : values.company_name || `${values.first_name} ${values.last_name}`.trim();
    const town = [values.postal_code, values.city].filter(Boolean).join(' ') + (values.province ? ` (${values.province})` : '');
    const address = [values.street, town.trim(), values.country].filter(Boolean).join(', ');

    return (
        <Section title={__('Billing details')} actions={<CopyButton text={billing.text}>{__('Copy billing details')}</CopyButton>}>
            <div className="mt-4 flex flex-wrap items-end gap-x-6 gap-y-3 rounded-lg bg-muted/50 p-4">
                <FormField
                    id="billing-tenant-id"
                    label={__('Tenant id (License Manager)')}
                    description={__('The id the License Manager knows this portal by. Unlike the slug, it never changes.')}
                    className="min-w-0 flex-1 basis-72"
                >
                    <CopyableInput id="billing-tenant-id" value={billing.tenant_id} className="max-w-sm [&_input]:font-mono [&_input]:text-sm [&_input]:font-semibold" />
                </FormField>

                <div className="flex flex-wrap items-center gap-2 pb-2">
                    <ColorBadge color={billing.complete ? 'emerald' : 'amber'}>{billing.complete ? __('Complete') : __('Incomplete')}</ColorBadge>
                    {!billing.complete && <span className="text-sm text-muted-foreground">{__('Missing: :items', { items: billing.gaps.join(', ') })}</span>}
                </div>
            </div>

            {billing.kind === null ? (
                <p className="mt-4 text-sm text-muted-foreground">{__('No billing details yet. Fill them in from the portal list: Edit.')}</p>
            ) : (
                <dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Detail label={__('Who pays')}>{billing.kind}</Detail>
                    <Detail label={values.company_name ? __('Legal business name') : __('Name')}>{legalName || '—'}</Detail>
                    {values.vat_number && <Detail label={__('VAT number')}>{values.vat_number}</Detail>}
                    <Detail label={__('Tax code')}>{values.tax_code || '—'}</Detail>
                    {values.company_name && <Detail label={__('SDI code')}>{values.sdi_code || '—'}</Detail>}
                    {values.company_name && <Detail label={__('PEC')}>{values.pec || '—'}</Detail>}
                    {values.contact_name && <Detail label={__('Contact person')}>{values.contact_name}</Detail>}
                    <Detail label={__('Street address')}>{address || '—'}</Detail>
                    <Detail label={__('Email')}>{values.email || '—'}</Detail>
                    <Detail label={__('Email for invoices and notices')}>{values.billing_email || '—'}</Detail>
                    <Detail label={__('Phone')}>{values.phone || '—'}</Detail>
                </dl>
            )}
        </Section>
    );
}

/** What the License Manager last said about the portal, whether it governs the portal, and the controls over that. */
function LicenseSection({ portal, license }: { portal: Props['portal']; license: Props['license'] }) {
    const enforcement = {
        off: __('Off: nothing changes and the License Manager is not called on its own.'),
        observe: __('Observing: it is checked and logged what would be blocked, nobody is blocked.'),
        on: __('On: the License Manager decides access and plan.'),
    }[license.enforcement];

    return (
        <Section
            title={__('License')}
            actions={
                <Button size="sm" variant="secondary" onClick={() => router.post(licenseRoutes.refresh.url(portal.slug), {}, { preserveScroll: true })}>
                    {__('Check now')}
                </Button>
            }
        >
            <p className="mt-1 text-sm text-muted-foreground">{enforcement}</p>

            {license.configuration_problem && (
                <p className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950 dark:text-red-100">
                    {__('The License Manager cannot be used with the current configuration (:code): check the license settings in the .env file.', { code: license.configuration_problem })}
                </p>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <ColorBadge color={license.color}>{license.state}</ColorBadge>
                {license.exempt && <ColorBadge color="zinc">{__('Outside the license')}</ColorBadge>}
                {license.stale && <ColorBadge color="amber">{__('Last answer kept: the License Manager did not answer')}</ColorBadge>}
                {license.would_block && license.enforcement === 'observe' && !license.exempt && (
                    <span className="text-sm text-muted-foreground">{__('It would be blocked: :reason.', { reason: license.would_block })}</span>
                )}
            </div>

            {license.alarm && (
                <p className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950 dark:text-red-100">
                    {__('The License Manager has not answered for a long time. The last known answer is still used, so nobody is penalised.')}
                </p>
            )}

            <dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Detail label={__('Ends on')}>{license.lifetime ? __('Never') : (license.expires_at ?? '—')}</Detail>
                <Detail label={__('Tolerance ends on')}>{license.grace_ends_at ?? '—'}</Detail>
                <Detail label={__('License plan')}>{license.plan ?? '—'}</Detail>
                <Detail label={__('Local plan applied')}>{license.local_plan ?? '—'}</Detail>
                <Detail label={__('Last check')}>{license.checked_at ?? '—'}</Detail>
                <Detail label={__('Still to collect')}>{license.balance ?? '—'}</Detail>
                {license.error && <Detail label={__('Last error')}>{license.error}</Detail>}
            </dl>

            {license.plan_unknown && (
                <p className="mt-3 text-sm text-amber-700 dark:text-amber-300">
                    {__('The plan name :plan matches no local plan: the most restrictive one is applied.', { plan: license.plan ?? '' })}
                </p>
            )}

            <div className="mt-5 flex items-center gap-3 border-t pt-5">
                <Switch
                    id="license-exempt"
                    checked={license.exempt}
                    onCheckedChange={(checked) => router.put(licenseRoutes.exempt.url(portal.slug), { exempt: checked }, { preserveScroll: true })}
                />
                <div>
                    <label htmlFor="license-exempt" className="text-sm font-medium">
                        {__('Outside the license')}
                    </label>
                    <p className="text-sm text-muted-foreground">{__('For demo and test portals not registered in the License Manager: they keep the local plan and paid period.')}</p>
                </div>
            </div>
        </Section>
    );
}

export default function Portal({ portal, manager, impersonationMinutes, billing, license, plan, subscription, mail, services, operators }: Props) {
    return (
        <>
            <Head title={portal.name} />

            <div className="flex w-full flex-col gap-6">
                <nav aria-label={__('Breadcrumb')} className="flex items-center gap-1 text-sm text-muted-foreground">
                    <Link href={portalsRoute.url()} className="hover:text-foreground">
                        {__('Portals')}
                    </Link>
                    <ChevronRight className="size-4" />
                    <span className="text-foreground">{portal.name}</span>
                </nav>

                <PageHeader
                    heading={portal.name}
                    subheading={portal.description}
                    actions={
                        <>
                            <ColorBadge color={portal.is_active ? 'emerald' : 'zinc'}>{portal.is_active ? __('Active') : __('Suspended')}</ColorBadge>
                            <Button size="sm" variant="secondary" onClick={() => router.patch(portalRoutes.toggle.url(portal.slug), {}, { preserveScroll: true })}>
                                {portal.is_active ? <PauseCircle /> : <PlayCircle />}
                                {portal.is_active ? __('Suspend') : __('Activate')}
                            </Button>
                            <Button size="sm" asChild>
                                <a href={portal.public_url} target="_blank" rel="noopener">
                                    {__('Public page')}
                                    <ExternalLink />
                                </a>
                            </Button>
                        </>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                    <StatCard icon={SquareStack} label={__('Services')} value={services.length} />
                    <StatCard icon={UsersRound} label={__('Operators')} value={operators.length} />
                    <StatCard icon={Globe} label={__('Slug')} value={`/p/${portal.slug}`} valueClassName="truncate text-xl sm:text-xl" />
                    <StatCard icon={Clock} label={__('Timezone')} value={portal.timezone} valueClassName="truncate text-xl sm:text-xl" />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Section title={__('Manager')}>
                        {manager ? (
                            <>
                                <div className="mt-4 flex items-center gap-3">
                                    <PersonAvatar person={manager} />
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">{manager.name}</div>
                                        <div className="truncate text-sm text-muted-foreground">{manager.email}</div>
                                    </div>
                                </div>

                                <div className="mt-4 space-y-2">
                                    <Button className="w-full" size="sm" onClick={() => router.post(portalRoutes.impersonate.url(portal.slug))}>
                                        <LogIn />
                                        {__('Enter as this manager')}
                                    </Button>
                                    <p className="text-sm text-muted-foreground">
                                        {__('You act as them for :minutes minutes, with a banner on every screen. Everything you do is written in the register under your name.', {
                                            minutes: impersonationMinutes,
                                        })}
                                    </p>
                                    <Button className="w-full" size="sm" variant="secondary" onClick={() => router.post(userRoutes.password.url(manager.id), {}, { preserveScroll: true })}>
                                        <KeyRound />
                                        {__('Issue new password')}
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <p className="mt-2 text-muted-foreground">{__('This portal has no manager account.')}</p>
                        )}
                    </Section>

                    <Section title={__('Contact details')} className="lg:col-span-2">
                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <Detail label={__('Email')}>{portal.contact.email ?? '—'}</Detail>
                            <Detail label={__('Phone')}>{portal.contact.phone ?? '—'}</Detail>
                            <Detail label={__('Website')}>{portal.contact.website ?? '—'}</Detail>
                            <Detail label={__('Address')}>{portal.contact.address ?? '—'}</Detail>
                        </dl>
                    </Section>
                </div>

                <BillingSection billing={billing} />

                <LicenseSection portal={portal} license={license} />

                <Section
                    title={__('Plan')}
                    actions={
                        <Button size="sm" variant="ghost" asChild>
                            <Link href={plansRoute.url()}>
                                <Layers />
                                {__('Plans')}
                            </Link>
                        </Button>
                    }
                >
                    <p className="mt-1 text-muted-foreground">
                        {plan ? plan.name : __('This portal is not on any plan.')}
                        {plan?.from_license && ` (${__('from the license')})`}
                        {plan?.price && ` · ${plan.price}`}
                    </p>

                    {plan && (
                        <>
                            <dl className="mt-4 grid gap-4 sm:grid-cols-4">
                                <Detail label={__('Services')}>{plan.services}</Detail>
                                <Detail label={__('Operators')}>{plan.operators}</Detail>
                                <Detail label={__('Bookings this month')}>
                                    <span className="flex items-center gap-2">
                                        {plan.bookings}
                                        {plan.bookings_threshold === 'reached' && <ColorBadge color="red">{__('Threshold reached')}</ColorBadge>}
                                        {plan.bookings_threshold === 'near' && <ColorBadge color="amber">{__('Near the threshold')}</ColorBadge>}
                                    </span>
                                </Detail>
                                <Detail label={__('Own SMTP')}>{plan.own_smtp ? __('Included') : __('No')}</Detail>
                            </dl>
                            <p className="mt-3 text-sm text-muted-foreground">
                                {__('New services and operators are refused past the limit. Bookings past the monthly threshold still go through: the manager is warned.')}
                            </p>
                        </>
                    )}

                    {subscription.governed ? (
                        <p className="mt-5 border-t pt-5 text-sm text-muted-foreground">{__('The paid period of this portal is managed by the License Manager.')}</p>
                    ) : (
                        <PaidUntilForm portal={portal} subscription={subscription} />
                    )}
                </Section>

                <MailSettingsPanel mail={mail} updateUrl={mailRoutes.update.url(portal.slug)} testUrl={mailRoutes.test.url(portal.slug)} />

                <section className="rounded-xl border">
                    <h2 className="border-b px-5 py-4 text-lg font-semibold">{__('Services')}</h2>
                    {services.length === 0 ? (
                        <p className="px-5 py-10 text-center text-muted-foreground">{__('The manager has not created any service yet.')}</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-5">{__('Service')}</TableHead>
                                    <TableHead>{__('Duration')}</TableHead>
                                    <TableHead>{__('Operators')}</TableHead>
                                    <TableHead className="pe-5">{__('Status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {services.map((service) => (
                                    <TableRow key={service.id}>
                                        <TableCell className="ps-5">
                                            <span className="flex items-center gap-2 font-medium">
                                                <span className="size-2.5 rounded-full" style={{ backgroundColor: service.color }} />
                                                {service.name}
                                            </span>
                                        </TableCell>
                                        <TableCell>{service.duration}</TableCell>
                                        <TableCell className="tabular-nums">{service.operators_count}</TableCell>
                                        <TableCell className="pe-5">
                                            <ColorBadge color={service.is_active ? 'emerald' : 'zinc'}>{service.is_active ? __('Published') : __('Hidden')}</ColorBadge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </section>

                <section className="rounded-xl border">
                    <h2 className="border-b px-5 py-4 text-lg font-semibold">{__('Operators')}</h2>
                    {operators.length === 0 ? (
                        <p className="px-5 py-10 text-center text-muted-foreground">{__('No operator has been added to this portal.')}</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-5">{__('Name')}</TableHead>
                                    <TableHead>{__('Email')}</TableHead>
                                    <TableHead>{__('Weekly slots')}</TableHead>
                                    <TableHead className="pe-5">{__('Status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {operators.map((operator) => (
                                    <TableRow key={operator.id}>
                                        <TableCell className="ps-5">
                                            <span className="flex items-center gap-2 font-medium">
                                                <PersonAvatar person={operator} className="size-6" />
                                                {operator.name}
                                            </span>
                                        </TableCell>
                                        <TableCell>{operator.email}</TableCell>
                                        <TableCell className="tabular-nums">{operator.weekly_slots}</TableCell>
                                        <TableCell className="pe-5">
                                            <ColorBadge color={operator.is_active ? 'emerald' : 'zinc'}>{operator.is_active ? __('Active') : __('Disabled')}</ColorBadge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </section>
            </div>

            <CredentialsDialog title={__('New password issued')} description={__('Share it with the manager. It is shown only once.')} />
        </>
    );
}
