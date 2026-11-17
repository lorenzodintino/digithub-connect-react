import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Ellipsis, ExternalLink, Eye, FileJson, KeyRound, Mail, PauseCircle, Pencil, PlayCircle, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { type BillingForm, BillingFields, billingFieldId, blankBilling } from '@/components/billing-fields';
import { ColorBadge } from '@/components/color-badge';
import { CopyIconButton } from '@/components/copy-icon-button';
import { CredentialsDialog } from '@/components/credentials-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useFilters } from '@/hooks/use-filters';
import { __, trans_choice } from '@/lib/i18n';
import { portals as portalsRoute } from '@/routes/admin';
import * as portalRoutes from '@/routes/admin/portals';
import type { Paginated } from '@/types/pagination';

type PortalForm = {
    name: string;
    slug: string;
    contact_email: string;
    contact_phone: string;
    website: string;
    address: string;
    timezone: string;
    locale: string;
    brand_color: string;
    description: string;
    is_active: boolean;
    billing: BillingForm;
};

interface Portal {
    id: number;
    license_id: string | null;
    slug: string;
    name: string;
    logo: string | null;
    initials: string;
    brand_color: string;
    public_url: string;
    manager: { name: string; email: string } | null;
    services_count: number;
    users_count: number;
    upcoming_count: number;
    is_active: boolean;
    mail_failing: boolean;
    json_gaps: string[];
    form: PortalForm;
}

interface Props {
    portals: Paginated<Portal>;
    filters: { q: string; status: string };
    timezones: string[];
    licenseSoftwareSet: boolean;
}

const blank: PortalForm = {
    name: '',
    slug: '',
    contact_email: '',
    contact_phone: '',
    website: '',
    address: '',
    timezone: 'Europe/Rome',
    locale: 'it',
    brand_color: '#4f46e5',
    description: '',
    is_active: true,
    billing: blankBilling,
};

/**
 * Move the cursor to the first field the server refused, in the order the
 * fields appear on the page (the server lists errors in the order of its rules).
 */
function focusFirstError(errors: Record<string, string>): void {
    const fields = Object.keys(errors)
        .map((key) => document.getElementById(key.startsWith('billing.') ? billingFieldId(key.slice('billing.'.length)) : `portal-${key.replace(/_/g, '-')}`))
        .filter((element): element is HTMLElement => element !== null)
        .sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));

    fields[0]?.focus();
}

/** What `Str::slug()` makes of a name: the server checks it anyway. */
function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function PortalDialog({ portal, onClose, timezones }: { portal: Portal | null; onClose: () => void; timezones: string[] }) {
    const form = useForm({ ...(portal?.form ?? blank), manager_name: '', manager_email: '' });
    const isNew = portal === null;

    function save(event: FormEvent): void {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose(), onError: focusFirstError };

        if (isNew) {
            form.post(portalRoutes.store.url(), options);
        } else {
            form.put(portalRoutes.update.url(portal.slug), options);
        }
    }

    function text(key: keyof PortalForm | 'manager_name' | 'manager_email', label: string, type = 'text', required = false) {
        return (
            <FormField id={`portal-${key}`} label={label} error={form.errors[key]}>
                <Input id={`portal-${key}`} type={type} required={required} value={form.data[key] as string} onChange={(event) => form.setData(key, event.target.value)} />
            </FormField>
        );
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={save} className="space-y-6">
                    <DialogHeader>
                        <DialogTitle>{isNew ? __('New portal') : __('Edit portal')}</DialogTitle>
                        <DialogDescription>{isNew ? __('Provision a portal and its single manager account.') : __('Update the details of this business.')}</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="portal-name" label={__('Business name')} error={form.errors.name}>
                            <Input
                                id="portal-name"
                                required
                                autoFocus
                                value={form.data.name}
                                onChange={(event) => {
                                    form.setData((data) => ({ ...data, name: event.target.value, ...(isNew ? { slug: slugify(event.target.value) } : {}) }));
                                }}
                            />
                        </FormField>
                        <FormField id="portal-slug" label={__('Public slug')} description={__('Used in the public link.')} error={form.errors.slug}>
                            <Input id="portal-slug" required value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} />
                        </FormField>
                        {text('contact_email', __('Contact email'), 'email')}
                        {text('contact_phone', __('Contact phone'))}
                        {text('website', __('Website'), 'url')}
                        {text('address', __('Address'))}

                        <FormField id="portal-timezone" label={__('Timezone')} error={form.errors.timezone}>
                            <Select value={form.data.timezone} onValueChange={(value) => form.setData('timezone', value)}>
                                <SelectTrigger id="portal-timezone" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {timezones.map((timezone) => (
                                        <SelectItem key={timezone} value={timezone}>
                                            {timezone}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField id="portal-locale" label={__('Default language')} error={form.errors.locale}>
                            <Select value={form.data.locale} onValueChange={(value) => form.setData('locale', value)}>
                                <SelectTrigger id="portal-locale" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="it">Italiano</SelectItem>
                                    <SelectItem value="en">English</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField id="portal-brand-color" label={__('Brand color')} error={form.errors.brand_color}>
                            <Input id="portal-brand-color" type="color" className="h-9 w-24 p-1" value={form.data.brand_color} onChange={(event) => form.setData('brand_color', event.target.value)} />
                        </FormField>
                    </div>

                    <FormField id="portal-description" label={__('Short description')} error={form.errors.description}>
                        <Textarea id="portal-description" rows={2} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                    </FormField>

                    <BillingFields
                        value={form.data.billing}
                        onChange={(field, value) => form.setData('billing', { ...form.data.billing, [field]: value })}
                        error={(field) => (form.errors as Record<string, string | undefined>)[`billing.${field}`]}
                    />

                    {isNew && (
                        <>
                            <div className="flex items-center gap-3">
                                <Separator className="flex-1" />
                                <span className="text-sm text-muted-foreground">{__('Manager account')}</span>
                                <Separator className="flex-1" />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {text('manager_name', __('Manager name'), 'text', true)}
                                {text('manager_email', __('Manager email'), 'email', true)}
                            </div>

                            <Alert>
                                <KeyRound />
                                <AlertDescription>{__('A password will be generated automatically and shown once after creation.')}</AlertDescription>
                            </Alert>
                        </>
                    )}

                    <div className="flex items-center gap-3">
                        <Switch id="portal-active" checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} />
                        <Label htmlFor="portal-active">{__('Portal active')}</Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isNew ? __('Create portal') : __('Save changes')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({ portal, onClose }: { portal: Portal | null; onClose: () => void }) {
    return (
        <Dialog open={portal !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Delete this portal?')}</DialogTitle>
                    <DialogDescription>{__('Manager, operators, services and availability of this portal will be permanently removed.')}</DialogDescription>
                </DialogHeader>

                {portal && portal.upcoming_count > 0 && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertDescription>
                            {trans_choice(
                                'This portal still has one upcoming booking: it will be deleted too, and no one will be notified.|This portal still has :count upcoming bookings: they will be deleted too, and no one will be notified.',
                                portal.upcoming_count,
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {__('Cancel')}
                    </Button>
                    <Button variant="destructive" onClick={() => portal && router.delete(portalRoutes.destroy.url(portal.slug), { preserveScroll: true, onSuccess: onClose })}>
                        {__('Delete portal')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Downloads the registration file of the portal for the License Manager. It is
 * disabled, saying why, while the billing details are incomplete or the
 * software code of this environment is not set: the file would not be valid.
 */
function LicenseJsonButton({ portal, ready }: { portal: Portal; ready: boolean }) {
    const reason = portal.json_gaps.length > 0 ? __('Complete the billing details first: :items.', { items: portal.json_gaps.join(', ') }) : !ready ? __('Set LICENSE_SOFTWARE_CODE in the .env file first.') : null;
    const label = reason ?? __('Download the customer data as JSON for the License Manager');

    return (
        <Button variant="ghost" size="icon" className="size-7" disabled={reason !== null} asChild={reason === null} aria-label={label} title={label}>
            {reason === null ? (
                // A file, not a page.
                <a href={portalRoutes.licenseJson.url(portal.slug)} download>
                    <FileJson />
                </a>
            ) : (
                <FileJson />
            )}
        </Button>
    );
}

function PortalBadge({ portal }: { portal: Portal }) {
    if (portal.logo) {
        return <img src={portal.logo} alt={portal.name} className="size-9 shrink-0 rounded-lg object-cover" />;
    }

    return (
        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg text-xs font-semibold text-white" style={{ backgroundColor: portal.brand_color }}>
            {portal.initials}
        </span>
    );
}

export default function Portals({ portals, filters: current, timezones, licenseSoftwareSet }: Props) {
    const [filters, setFilter] = useFilters(portalsRoute.url(), current, { q: '', status: 'all' });
    const [editing, setEditing] = useState<Portal | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Portal | null>(null);

    return (
        <>
            <Head title={__('Portals')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader
                    heading={__('Portals')}
                    subheading={__('Every business using the platform has its own portal and one manager account.')}
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            {__('New portal')}
                        </Button>
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <SearchInput id="portal-search" value={filters.q} onChange={(value) => setFilter('q', value)} placeholder={__('Search by name, slug, email or tenant')} className="sm:max-w-sm sm:flex-1" />

                    <FormField id="portal-status" label={__('Status')} className="sm:w-40">
                        <Select value={filters.status} onValueChange={(value) => setFilter('status', value)}>
                            <SelectTrigger id="portal-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{__('All statuses')}</SelectItem>
                                <SelectItem value="active">{__('Active')}</SelectItem>
                                <SelectItem value="suspended">{__('Suspended')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>

                {portals.data.length === 0 ? (
                    <EmptyState icon={Building2} title={__('No portals yet')}>
                        <p>{__('Create the first portal to onboard a business.')}</p>
                        <Button className="mt-4" onClick={() => setEditing('new')}>
                            <Plus />
                            {__('New portal')}
                        </Button>
                    </EmptyState>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-4">{__('Portal')}</TableHead>
                                    <TableHead>{__('Manager')}</TableHead>
                                    <TableHead>{__('Services')}</TableHead>
                                    <TableHead>{__('People')}</TableHead>
                                    <TableHead>{__('Status')}</TableHead>
                                    <TableHead>{__('Tenant')}</TableHead>
                                    <TableHead className="pe-4" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {portals.data.map((portal) => (
                                    <TableRow key={portal.id}>
                                        <TableCell className="ps-4">
                                            <div className="flex items-center gap-3">
                                                <PortalBadge portal={portal} />
                                                <div className="min-w-0">
                                                    <Link href={portalRoutes.show.url(portal.slug)} className="block truncate font-medium hover:underline">
                                                        {portal.name}
                                                    </Link>
                                                    <span className="block truncate text-sm text-muted-foreground">/p/{portal.slug}</span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {portal.manager ? (
                                                <div className="min-w-0">
                                                    <div className="truncate">{portal.manager.name}</div>
                                                    <div className="truncate text-sm text-muted-foreground">{portal.manager.email}</div>
                                                </div>
                                            ) : (
                                                <ColorBadge color="amber">{__('Not assigned')}</ColorBadge>
                                            )}
                                        </TableCell>
                                        <TableCell className="tabular-nums">{portal.services_count}</TableCell>
                                        <TableCell className="tabular-nums">{portal.users_count}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-1">
                                                <ColorBadge color={portal.is_active ? 'emerald' : 'zinc'}>{portal.is_active ? __('Active') : __('Suspended')}</ColorBadge>
                                                {portal.mail_failing && (
                                                    <ColorBadge color="red">
                                                        <Mail className="size-3" />
                                                        {__('Email stuck')}
                                                    </ColorBadge>
                                                )}
                                            </div>
                                        </TableCell>
                                        {/* The id the License Manager knows the portal by: an UUID that never changes, unlike the slug. Shown short, copied whole. */}
                                        <TableCell>
                                            {portal.license_id && (
                                                <span className="flex items-center gap-1">
                                                    <code className="font-mono text-sm" title={portal.license_id}>
                                                        {portal.license_id.slice(0, 8)}…
                                                    </code>
                                                    <CopyIconButton text={portal.license_id} label={__('Copy tenant id')} />
                                                    <LicenseJsonButton portal={portal} ready={licenseSoftwareSet} />
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="pe-4 text-end">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button size="icon" variant="ghost" aria-label={__('Portal actions')}>
                                                        <Ellipsis />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onSelect={() => setEditing(portal)}>
                                                        <Pencil />
                                                        {__('Edit')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={portalRoutes.show.url(portal.slug)}>
                                                            <Eye />
                                                            {__('Details')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <a href={portal.public_url} target="_blank" rel="noopener">
                                                            <ExternalLink />
                                                            {__('Public page')}
                                                        </a>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem onSelect={() => router.patch(portalRoutes.toggle.url(portal.slug), {}, { preserveScroll: true })}>
                                                        {portal.is_active ? <PauseCircle /> : <PlayCircle />}
                                                        {portal.is_active ? __('Suspend') : __('Activate')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem variant="destructive" onSelect={() => setDeleting(portal)}>
                                                        <Trash2 />
                                                        {__('Delete')}
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={portals} />
            </div>

            {editing !== null && (
                <PortalDialog key={editing === 'new' ? 'new' : editing.id} portal={editing === 'new' ? null : editing} onClose={() => setEditing(null)} timezones={timezones} />
            )}
            <DeleteDialog portal={deleting} onClose={() => setDeleting(null)} />
            <CredentialsDialog title={__('Portal created')} description={__('Share these credentials with the business. The password is shown only once.')} />
        </>
    );
}
