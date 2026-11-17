import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Download, ExternalLink, Trash2, TriangleAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { CopyableInput } from '@/components/copyable-input';
import { FormField } from '@/components/form-field';
import { type MailSettings, MailSettingsPanel } from '@/components/mail-settings';
import { PageHeader } from '@/components/page-header';
import { PalettePicker } from '@/components/palette-picker';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { UpgradeRequest } from '@/components/upgrade-request';
import { usePalette } from '@/hooks/use-palette';
import { __ } from '@/lib/i18n';
import * as portalRoutes from '@/routes/app/portal';
import { accept } from '@/routes/app/portal/documents';
import * as fileRoutes from '@/routes/app/portal/files';
import * as mailRoutes from '@/routes/app/portal/mail';

interface PortalForm {
    name: string;
    slug: string;
    description: string;
    brand_color: string;
    palette: string;
    timezone: string;
    locale: string;
    contact_email: string;
    contact_phone: string;
    website: string;
    address: string;
    privacy_policy_url: string;
    terms_url: string;
    data_controller: string;
    data_retention_days: string;
}

interface Props {
    portal: PortalForm;
    logo: { url: string | null; initials: string; background: string; foreground: string };
    files: { privacy: string | null; terms: string | null };
    hasLegalDocuments: boolean;
    publicUrl: string;
    embedSnippet: string;
    embedAvailable: boolean;
    planRequest: { id: number; requested: string; status: string; label: string; color: string; response: string | null; open: boolean; date: string } | null;
    palettes: { value: string; label: string }[];
    timezones: string[];
    mail: MailSettings;
    plan: {
        name: string | null;
        state: string | null;
        state_color: string | null;
        paid_until: string | null;
        usage: { label: string; value: string }[] | null;
    } | null;
    documents: {
        id: number;
        label: string;
        url: string;
        version: string;
        status: 'read' | 'accepted' | 'manager_only' | 'accept';
        accepted: string | null;
        confirm: string;
    }[];
}

function Card({ id, title, description, children }: { id?: string; title: string; description?: ReactNode; children: ReactNode }) {
    return (
        <section id={id} className="space-y-6 rounded-xl border p-5">
            <div>
                <h2 className="text-lg font-semibold">{title}</h2>
                {description && <p className="mt-1 text-muted-foreground">{description}</p>}
            </div>
            {children}
        </section>
    );
}

/** A file picked here goes up at once and replaces the previous one. */
function Upload({ kind, label, accept: types, current, description }: { kind: 'logo' | 'privacy' | 'terms'; label: string; accept: string; current: ReactNode; description?: string }) {
    const { errors } = usePage().props;

    return (
        <FormField id={`upload-${kind}`} label={label} description={description} error={errors[kind]}>
            <div className="mt-2 flex flex-wrap items-center gap-2">
                <Input
                    id={`upload-${kind}`}
                    type="file"
                    accept={types}
                    className="max-w-xs"
                    onChange={(event) => {
                        const file = event.target.files?.[0];
                        event.target.value = '';

                        if (file) {
                            router.post(fileRoutes.store.url(kind), { [kind]: file }, { forceFormData: true, preserveScroll: true });
                        }
                    }}
                />
                {current}
            </div>
        </FormField>
    );
}

function RemoveFile({ kind }: { kind: 'logo' | 'privacy' | 'terms' }) {
    return (
        <Button type="button" size="sm" variant="ghost" onClick={() => router.delete(fileRoutes.destroy.url(kind), { preserveScroll: true })}>
            <Trash2 />
            {__('Remove')}
        </Button>
    );
}

function DocumentFile({ kind, label, url }: { kind: 'privacy' | 'terms'; label: string; url: string | null }) {
    return (
        <Upload
            kind={kind}
            label={label}
            accept="application/pdf"
            current={
                url && (
                    <>
                        <Button type="button" size="sm" variant="ghost" asChild>
                            <a href={url} target="_blank" rel="noopener">
                                <ExternalLink />
                                {__('Open')}
                            </a>
                        </Button>
                        <RemoveFile kind={kind} />
                    </>
                )
            }
        />
    );
}

function PortalLogo({ logo, name }: { logo: Props['logo']; name: string }) {
    if (logo.url) {
        return <img src={logo.url} alt={name} className="size-16 shrink-0 rounded-2xl object-cover" />;
    }

    return (
        <span className="flex size-16 shrink-0 items-center justify-center rounded-2xl text-xl font-semibold" style={{ backgroundColor: logo.background, color: logo.foreground }}>
            {logo.initials}
        </span>
    );
}

export default function PortalSettings(props: Props) {
    const { portal, logo, files, plan, documents } = props;
    const form = useForm<Omit<PortalForm, 'slug'>>(Object.fromEntries(Object.entries(portal).filter(([key]) => key !== 'slug')) as Omit<PortalForm, 'slug'>);
    const data = form.data;
    // The page shows the palette at once; the business keeps it only when the form is saved.
    const { update: previewPalette } = usePalette();

    function save(event: FormEvent): void {
        event.preventDefault();
        form.put(portalRoutes.update.url(), { preserveScroll: true });
    }

    function text(key: keyof typeof data, label: string, options: { type?: string; description?: string; placeholder?: string; required?: boolean } = {}) {
        return (
            <FormField id={`portal-${key}`} label={label} description={options.description} error={form.errors[key]}>
                <Input
                    id={`portal-${key}`}
                    type={options.type ?? 'text'}
                    required={options.required}
                    placeholder={options.placeholder}
                    value={data[key]}
                    onChange={(event) => form.setData(key, event.target.value)}
                />
            </FormField>
        );
    }

    const saveButton = (
        <div className="flex justify-end">
            <Button type="submit" disabled={form.processing}>
                {__('Save changes')}
            </Button>
        </div>
    );

    return (
        <>
            <Head title={__('Portal settings')} />

            <div className="flex w-full max-w-4xl flex-col gap-6">
                <PageHeader heading={__('Portal settings')} subheading={__('How your portal looks and how customers reach you.')} />

                <Card title={__('Share your booking page')} description={__('Send the link, or embed the page inside your own website.')}>
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label>{__('Public link')}</Label>
                            <CopyableInput value={props.publicUrl} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="embed-code">{__('Embed code')}</Label>
                            <Textarea id="embed-code" readOnly rows={3} disabled={!props.embedAvailable} className="font-mono text-xs" value={props.embedSnippet} onFocus={(event) => event.currentTarget.select()} />
                            {!props.embedAvailable && (
                                <div className="flex flex-wrap items-center gap-3" data-test="embed-locked">
                                    <p className="text-sm text-muted-foreground">{__('Embedding the page in your website is not included in your plan.')}</p>
                                    <UpgradeRequest reason="embed" />
                                </div>
                            )}
                        </div>
                    </div>
                </Card>

                <MailSettingsPanel mail={props.mail} updateUrl={mailRoutes.update.url()} testUrl={mailRoutes.test.url()} />

                <form onSubmit={save}>
                    <Card
                        title={__('Privacy and terms')}
                        description={__('Your booking page asks every customer to accept these before they book. The documents are yours: publish them on your website and link them here.')}
                    >
                        {!props.hasLegalDocuments && (
                            <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                                <TriangleAlert />
                                <AlertDescription>{__('No privacy policy is linked yet. Customers are still asked for consent, but they have nothing to read first.')}</AlertDescription>
                            </Alert>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            {text('privacy_policy_url', __('Privacy policy link'), { type: 'url', placeholder: 'https://…', description: __('Leave empty if you upload the document below.') })}
                            {text('terms_url', __('Terms of service link'), { type: 'url', placeholder: 'https://…', description: __('Leave empty if you upload the document below.') })}
                            {text('data_controller', __('Data controller'), { description: __('Who answers for these data: company name, or the professional in charge.') })}
                            <FormField
                                id="portal-data_retention_days"
                                label={__('Keep customer data for (days)')}
                                description={__('Leave empty to keep it indefinitely. Past this many days, name, email, phone and notes are erased from old bookings; the appointment stays for your records.')}
                                error={form.errors.data_retention_days}
                            >
                                <Input
                                    id="portal-data_retention_days"
                                    type="number"
                                    min={30}
                                    max={3650}
                                    value={data.data_retention_days}
                                    onChange={(event) => form.setData('data_retention_days', event.target.value)}
                                />
                            </FormField>
                        </div>

                        <div className="flex items-center gap-3">
                            <Separator className="flex-1" />
                            <span className="text-sm text-muted-foreground">{__('Or upload the documents')}</span>
                            <Separator className="flex-1" />
                        </div>

                        <p className="text-sm text-muted-foreground">
                            {__('No website to publish them on? Upload each document as a PDF and we will link it for you. An uploaded file wins over the link above.')}
                        </p>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <DocumentFile kind="privacy" label={__('Privacy policy (PDF)')} url={files.privacy} />
                            <DocumentFile kind="terms" label={__('Terms of service (PDF)')} url={files.terms} />
                        </div>

                        {saveButton}
                    </Card>
                </form>

                <form onSubmit={save}>
                    <Card title={__('Business details')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {text('name', __('Business name'), { required: true })}
                            <FormField id="portal-slug" label={__('Public slug')} description={__('Only the platform administrator can change it.')}>
                                <Input id="portal-slug" value={portal.slug} readOnly />
                            </FormField>
                            {text('contact_email', __('Contact email'), { type: 'email', description: __('Used as the reply address when none is set under Email delivery.') })}
                            {text('contact_phone', __('Contact phone'))}
                            {text('website', __('Website'), { type: 'url' })}
                            {text('address', __('Address'))}

                            <FormField id="portal-timezone" label={__('Timezone')} error={form.errors.timezone}>
                                <Select value={data.timezone} onValueChange={(value) => form.setData('timezone', value)}>
                                    <SelectTrigger id="portal-timezone" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {props.timezones.map((timezone) => (
                                            <SelectItem key={timezone} value={timezone}>
                                                {timezone}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField id="portal-locale" label={__('Public page language')} error={form.errors.locale}>
                                <Select value={data.locale} onValueChange={(value) => form.setData('locale', value)}>
                                    <SelectTrigger id="portal-locale" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="it">Italiano</SelectItem>
                                        <SelectItem value="en">English</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField id="portal-brand_color" label={__('Brand color')} error={form.errors.brand_color}>
                                <Input id="portal-brand_color" type="color" className="h-10 w-20 p-1" value={data.brand_color} onChange={(event) => form.setData('brand_color', event.target.value)} />
                            </FormField>
                        </div>

                        <FormField
                            id="portal-palette"
                            label={__('Palette')}
                            description={__('The colours of your booking page and of your back office, combined with your brand color. Light or dark follows each person.')}
                            error={form.errors.palette}
                        >
                            <div className="mt-2">
                                <PalettePicker
                                    value={data.palette}
                                    options={props.palettes}
                                    onChange={(palette, origin) => {
                                        form.setData('palette', palette);
                                        previewPalette(palette, origin, false);
                                    }}
                                />
                            </div>
                        </FormField>

                        <FormField id="portal-description" label={__('Short description')} error={form.errors.description}>
                            <Textarea id="portal-description" rows={3} value={data.description} onChange={(event) => form.setData('description', event.target.value)} />
                        </FormField>

                        <Upload
                            kind="logo"
                            label={__('Logo')}
                            description={__('Shown on your booking page. JPG, PNG or WebP, up to 1 MB.')}
                            accept="image/jpeg,image/png,image/webp"
                            current={
                                <>
                                    <span className="order-first me-2">
                                        <PortalLogo logo={logo} name={portal.name} />
                                    </span>
                                    {logo.url && <RemoveFile kind="logo" />}
                                </>
                            }
                        />

                        {saveButton}
                    </Card>
                </form>

                {plan && (
                    <section id="your-plan" className="scroll-mt-6 rounded-xl border p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">{__('Your plan')}</h2>
                                <p className="mt-1 text-muted-foreground">{plan.name ?? __('No plan')}</p>
                                <UpgradeRequest className="mt-3" reason="other" />
                                {props.planRequest && !props.planRequest.open && (
                                    <div className="mt-3 space-y-1 text-sm" data-test="plan-request-last">
                                        <p className="flex flex-wrap items-center gap-2">
                                            <ColorBadge color={props.planRequest.color}>{props.planRequest.label}</ColorBadge>
                                            <span className="text-muted-foreground">
                                                {__('Your request for the :plan plan', { plan: props.planRequest.requested })} · {props.planRequest.date}
                                            </span>
                                        </p>
                                        {props.planRequest.response && <p className="break-words text-muted-foreground">{props.planRequest.response}</p>}
                                    </div>
                                )}
                            </div>
                            {plan.paid_until && (
                                <div className="text-end">
                                    {plan.state && <ColorBadge color={plan.state_color ?? 'zinc'}>{plan.state}</ColorBadge>}
                                    <p className="mt-1 text-sm text-muted-foreground">{__('Paid until :date', { date: plan.paid_until })}</p>
                                </div>
                            )}
                        </div>

                        {plan.usage && (
                            <dl className="mt-4 grid gap-4 sm:grid-cols-3">
                                {plan.usage.map((item) => (
                                    <div key={item.label}>
                                        <dt className="text-sm text-muted-foreground">{item.label}</dt>
                                        <dd className="font-medium tabular-nums">{item.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </section>
                )}

                {documents.length > 0 && (
                    <Card
                        id="platform-documents"
                        title={__('Platform documents')}
                        description={__('The agreements between your business and the platform. Read each new version; the ones that bind your portal ask for your acceptance.')}
                    >
                        <ul className="divide-y">
                            {documents.map((document) => (
                                <li key={document.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <div>
                                        <a href={document.url} target="_blank" rel="noopener" className="font-medium underline">
                                            {document.label}
                                        </a>
                                        <p className="text-sm text-muted-foreground">{document.version}</p>
                                    </div>

                                    {document.status === 'read' && <ColorBadge color="zinc">{__('To read')}</ColorBadge>}
                                    {document.status === 'accepted' && (
                                        <ColorBadge color="emerald">
                                            <Check />
                                            {document.accepted}
                                        </ColorBadge>
                                    )}
                                    {document.status === 'manager_only' && <ColorBadge color="amber">{__('The manager has to accept it personally')}</ColorBadge>}
                                    {document.status === 'accept' && (
                                        <Button
                                            size="sm"
                                            onClick={() => {
                                                if (window.confirm(document.confirm)) {
                                                    router.post(accept.url(document.id), {}, { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            {__('Accept')}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Card
                    title={__('Your data')}
                    description={__('Download a copy of everything your portal holds: settings, staff, services, customers, bookings, the register and the files you uploaded. Passwords and access keys are not included.')}
                >
                    <div>
                        {/* A file, not a page. */}
                        <Button variant="outline" asChild>
                            <a href={portalRoutes.exportMethod.url()}>
                                <Download />
                                {__('Download all data (.zip)')}
                            </a>
                        </Button>
                    </div>
                </Card>
            </div>
        </>
    );
}
