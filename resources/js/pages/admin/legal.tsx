import { Head, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __, trans_choice } from '@/lib/i18n';
import * as legalRoutes from '@/routes/admin/legal';

interface LegalDocument {
    type: string;
    label: string;
    requires_acceptance: boolean;
    versions: { id: number; version: string; url: string; published_at: string; acceptances: number; in_force: boolean }[];
}

type Acceptance =
    | { status: 'unpublished' }
    | { status: 'missing' }
    | { status: 'current'; version: string; accepted_at: string; accepted_by: string }
    | { status: 'old'; version: string };

interface Props {
    documents: LegalDocument[];
    columns: { type: string; label: string }[];
    portals: { id: number; name: string; accepted: Record<string, Acceptance> }[];
}

function PublishDialog({ document, onClose }: { document: LegalDocument; onClose: () => void }) {
    const form = useForm<{ version: string; file: File | null }>({ version: '', file: null });

    function publish(event: FormEvent): void {
        event.preventDefault();
        form.post(legalRoutes.store.url(document.type), { forceFormData: true, preserveScroll: true, onSuccess: () => onClose() });
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={publish} className="space-y-6">
                    <DialogHeader>
                        <DialogTitle>{__('Publish a new version')}</DialogTitle>
                        <DialogDescription>
                            {document.label}. {__('It is in force from now on; earlier versions and their acceptances are kept.')}
                        </DialogDescription>
                    </DialogHeader>

                    <FormField id="legal-version" label={__('Version')} error={form.errors.version}>
                        <Input id="legal-version" required placeholder={__('e.g. 2026-10')} value={form.data.version} onChange={(event) => form.setData('version', event.target.value)} />
                    </FormField>

                    <FormField id="legal-file" label={__('PDF document')} error={form.errors.file}>
                        <Input id="legal-file" type="file" accept="application/pdf" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} />
                    </FormField>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {__('Publish')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AcceptanceCell({ acceptance }: { acceptance: Acceptance | undefined }) {
    switch (acceptance?.status) {
        case 'missing':
            return <ColorBadge color="red">{__('Not accepted')}</ColorBadge>;
        case 'current':
            return (
                <div className="space-y-1">
                    <ColorBadge color="emerald">{acceptance.version}</ColorBadge>
                    <div className="text-sm text-muted-foreground">
                        {acceptance.accepted_at} · {acceptance.accepted_by}
                    </div>
                </div>
            );
        case 'old':
            return <ColorBadge color="amber">{__('Old version :version', { version: acceptance.version })}</ColorBadge>;
        default:
            return <span className="text-muted-foreground">—</span>;
    }
}

export default function Legal({ documents, columns, portals }: Props) {
    const [publishing, setPublishing] = useState<LegalDocument | null>(null);

    return (
        <>
            <Head title={__('Legal documents')} />

            <div className="flex w-full max-w-5xl flex-col gap-6">
                <PageHeader heading={__('Legal documents')} subheading={__('What the platform publishes towards the businesses it serves, and which version each portal accepted.')} />

                <div className="grid gap-4 md:grid-cols-2">
                    {documents.map((document) => (
                        <section key={document.type} className="flex flex-col gap-3 rounded-xl border p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">{document.label}</h2>
                                    <p className="text-sm text-muted-foreground">
                                        {document.requires_acceptance ? __('Every portal must accept each new version.') : __('Shown to every portal, no acceptance needed.')}
                                    </p>
                                </div>

                                <Button size="sm" variant="secondary" onClick={() => setPublishing(document)}>
                                    <Upload />
                                    {__('New version')}
                                </Button>
                            </div>

                            {document.versions.length === 0 ? (
                                <p className="text-sm text-muted-foreground italic">{__('Not published yet.')}</p>
                            ) : (
                                <ul className="divide-y text-sm">
                                    {document.versions.map((version) => (
                                        <li key={version.id} className="flex items-center justify-between gap-3 py-2">
                                            <span className="flex items-center gap-2">
                                                <a href={version.url} target="_blank" rel="noopener" className="font-medium underline">
                                                    {version.version}
                                                </a>
                                                {version.in_force && <ColorBadge color="emerald">{__('In force')}</ColorBadge>}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {version.published_at}
                                                {document.requires_acceptance && ` · ${trans_choice('accepted by one portal|accepted by :count portals', version.acceptances)}`}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>

                <section className="rounded-xl border">
                    <h2 className="px-5 pt-5 text-lg font-semibold">{__('Acceptance by portal')}</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="ps-5">{__('Portal')}</TableHead>
                                {columns.map((column) => (
                                    <TableHead key={column.type}>{column.label}</TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {portals.map((portal) => (
                                <TableRow key={portal.id}>
                                    <TableCell className="ps-5 font-medium">{portal.name}</TableCell>
                                    {columns.map((column) => (
                                        <TableCell key={column.type}>
                                            <AcceptanceCell acceptance={portal.accepted[column.type]} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            </div>

            {publishing && <PublishDialog document={publishing} onClose={() => setPublishing(null)} />}
        </>
    );
}
