import { Head, router, useForm } from '@inertiajs/react';
import { Clock, Info, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { FormField, InputError } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { __, trans_choice } from '@/lib/i18n';
import * as resourceRoutes from '@/routes/app/resources';
import * as blockRoutes from '@/routes/app/resources/blocks';

interface Resource {
    id: number;
    name: string;
    type: string;
    type_label: string;
    description: string;
    notes: string;
    preparation_minutes: number;
    cleanup_minutes: number;
    is_active: boolean;
    blocks: { id: number; period: string; reason: string | null }[];
}

interface Props {
    resources: Resource[];
    types: { value: string; label: string }[];
    timezone: string;
}

function ResourceDialog({ resource, open, onClose, types }: { resource: Resource | null; open: boolean; onClose: () => void; types: Props['types'] }) {
    const form = useForm({
        name: resource?.name ?? '',
        type: resource?.type ?? 'room',
        description: resource?.description ?? '',
        notes: resource?.notes ?? '',
        preparation_minutes: String(resource?.preparation_minutes ?? 0),
        cleanup_minutes: String(resource?.cleanup_minutes ?? 0),
        is_active: resource?.is_active ?? true,
    });

    function save(event: FormEvent): void {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (resource === null) {
            form.post(resourceRoutes.store.url(), options);
        } else {
            form.put(resourceRoutes.update.url(resource.id), options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={save} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>{resource ? __('Edit resource') : __('New resource')}</DialogTitle>
                    </DialogHeader>

                    <FormField id="resource-name" label={__('Resource name')} error={form.errors.name}>
                        <Input id="resource-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required autoFocus />
                    </FormField>

                    <FormField id="resource-type" label={__('Resource type')} error={form.errors.type}>
                        <Select value={form.data.type} onValueChange={(value) => form.setData('type', value)}>
                            <SelectTrigger id="resource-type" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField id="resource-description" label={__('Description')} error={form.errors.description}>
                        <Textarea id="resource-description" rows={2} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                    </FormField>

                    <FormField id="resource-notes" label={__('Internal notes')} description={__('Visible only in the management panel.')} error={form.errors.notes}>
                        <Textarea id="resource-notes" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="resource-preparation" label={__('Preparation (minutes)')} error={form.errors.preparation_minutes}>
                            <Input
                                id="resource-preparation"
                                type="number"
                                min={0}
                                max={240}
                                required
                                value={form.data.preparation_minutes}
                                onChange={(event) => form.setData('preparation_minutes', event.target.value)}
                            />
                        </FormField>
                        <FormField id="resource-cleanup" label={__('Cleanup (minutes)')} error={form.errors.cleanup_minutes}>
                            <Input
                                id="resource-cleanup"
                                type="number"
                                min={0}
                                max={240}
                                required
                                value={form.data.cleanup_minutes}
                                onChange={(event) => form.setData('cleanup_minutes', event.target.value)}
                            />
                        </FormField>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-start gap-3">
                            <Switch id="resource-active" checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} />
                            <div className="grid gap-1">
                                <Label htmlFor="resource-active">{__('Active')}</Label>
                                <p className="text-sm text-muted-foreground">{__('Deactivate a resource to keep its details and service assignments.')}</p>
                            </div>
                        </div>
                        <InputError message={form.errors.is_active} />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {__('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BlockDialog({ resource, onClose, timezone }: { resource: Resource | null; onClose: () => void; timezone: string }) {
    const form = useForm({ starts_at: '', ends_at: '', reason: '' });

    function save(event: FormEvent): void {
        event.preventDefault();

        if (resource === null) {
            return;
        }

        form.post(blockRoutes.store.url(resource.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    }

    return (
        <Dialog open={resource !== null} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={save} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>{__('Add unavailability')}</DialogTitle>
                        <DialogDescription>
                            {resource?.name}
                            <br />
                            {__('Enter times in the portal timezone: :timezone.', { timezone })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="block-start" label={__('Start')} error={form.errors.starts_at}>
                            <Input id="block-start" type="datetime-local" required value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} />
                        </FormField>
                        <FormField id="block-end" label={__('End')} error={form.errors.ends_at}>
                            <Input id="block-end" type="datetime-local" required value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} />
                        </FormField>
                    </div>

                    <FormField id="block-reason" label={__('Reason')} description={__('Optional. For example, maintenance or repairs.')} error={form.errors.reason}>
                        <Input id="block-reason" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                    </FormField>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {__('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({ resource, onClose }: { resource: Resource | null; onClose: () => void }) {
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    function remove(): void {
        if (resource === null) {
            return;
        }

        router.delete(resourceRoutes.destroy.url(resource.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => close(),
            onError: (errors) => setError(errors.resource),
        });
    }

    function close(): void {
        setError(undefined);
        onClose();
    }

    return (
        <Dialog open={resource !== null} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Delete resource?')}</DialogTitle>
                    <DialogDescription>{__('Its unavailability periods will also be removed. Deactivate it if you want to keep them.')}</DialogDescription>
                </DialogHeader>

                <InputError message={error} />

                <DialogFooter>
                    <Button variant="outline" onClick={close}>
                        {__('Cancel')}
                    </Button>
                    <Button variant="destructive" onClick={remove} disabled={processing}>
                        {__('Delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Resources({ resources, types, timezone }: Props) {
    const [editing, setEditing] = useState<{ resource: Resource | null } | null>(null);
    const [blocking, setBlocking] = useState<Resource | null>(null);
    const [deleting, setDeleting] = useState<Resource | null>(null);

    const newResource = (
        <Button onClick={() => setEditing({ resource: null })}>
            <Plus />
            {__('New resource')}
        </Button>
    );

    return (
        <>
            <Head title={__('Resources')} />

            <div className="flex flex-col gap-6">
                <PageHeader heading={__('Resources')} subheading={__('Rooms, cabins and equipment shared by your services.')} actions={newResource} />

                <Alert>
                    <Info />
                    <AlertTitle>{__('Resource configuration')}</AlertTitle>
                    <AlertDescription>
                        {__('Booking times require a free operator and all the resources needed by the service. Preparation, cleanup and unavailability are included.')}
                    </AlertDescription>
                </Alert>

                {resources.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center">
                        <h2 className="font-medium">{__('No resources yet')}</h2>
                        <p className="mt-2 text-muted-foreground">{__('Create a separate resource for each physical room, cabin or piece of equipment.')}</p>
                        <Button className="mt-4" variant="outline" onClick={() => setEditing({ resource: null })}>
                            {__('New resource')}
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {resources.map((resource) => (
                            <div key={resource.id} className="space-y-4 rounded-xl border p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h2 className="font-medium break-words">{resource.name}</h2>
                                        <p className="text-sm text-muted-foreground">{resource.type_label}</p>
                                    </div>
                                    <ColorBadge color={resource.is_active ? 'emerald' : 'zinc'}>{resource.is_active ? __('Active') : __('Inactive')}</ColorBadge>
                                </div>

                                {resource.description && <p className="text-sm break-words text-muted-foreground">{resource.description}</p>}

                                <div className="flex flex-wrap gap-2">
                                    <ColorBadge color="zinc">
                                        {trans_choice('one minute of preparation|:count minutes of preparation', resource.preparation_minutes)}
                                    </ColorBadge>
                                    <ColorBadge color="zinc">{trans_choice('one minute of cleanup|:count minutes of cleanup', resource.cleanup_minutes)}</ColorBadge>
                                </div>

                                {resource.notes && (
                                    <div className="space-y-1">
                                        <h3 className="text-sm font-medium">{__('Internal notes')}</h3>
                                        <p className="text-sm break-words text-muted-foreground">{resource.notes}</p>
                                    </div>
                                )}

                                {resource.blocks.length > 0 && (
                                    <div className="space-y-2 border-t pt-3">
                                        <h3 className="text-sm font-medium">{__('Unavailability')}</h3>
                                        <p className="text-sm text-muted-foreground">{__('Times are shown in :timezone.', { timezone })}</p>
                                        {resource.blocks.map((block) => (
                                            <div key={block.id} className="flex items-start justify-between gap-3">
                                                <div className="min-w-0 text-sm">
                                                    <p className="tabular-nums">{block.period}</p>
                                                    {block.reason && <p className="break-words text-muted-foreground">{block.reason}</p>}
                                                </div>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={__('Remove unavailability')}
                                                    onClick={() => router.delete(blockRoutes.destroy.url(block.id), { preserveScroll: true })}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-2 border-t pt-4">
                                    <Button size="sm" variant="outline" onClick={() => setEditing({ resource })}>
                                        {__('Edit')}
                                    </Button>
                                    <Button size="sm" variant="outline" onClick={() => setBlocking(resource)}>
                                        <Clock />
                                        {__('Add unavailability')}
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => setDeleting(resource)}>
                                        {__('Delete')}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {editing && (
                <ResourceDialog key={editing.resource?.id ?? 'new'} resource={editing.resource} open onClose={() => setEditing(null)} types={types} />
            )}
            <BlockDialog resource={blocking} onClose={() => setBlocking(null)} timezone={timezone} />
            <DeleteDialog resource={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}
