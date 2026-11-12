import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Ellipsis, Pencil, Plus, Trash2, TriangleAlert, UsersRound } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { CredentialsDialog } from '@/components/credentials-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField, InputError } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { __, trans_choice } from '@/lib/i18n';
import * as staffRoutes from '@/routes/app/staff';
import * as photoRoutes from '@/routes/app/staff/photo';

interface Operator {
    id: number;
    name: string;
    email: string;
    initials: string;
    avatar: string | null;
    phone: string;
    job_title: string;
    bio: string;
    timezone: string;
    locale: string;
    is_bookable: boolean;
    is_active: boolean;
    service_ids: number[];
    service_names: string[];
    weekly_slots: number;
    upcoming_count: number;
}

interface Props {
    operators: Operator[];
    services: { id: number; name: string }[];
    managerBookable: boolean;
    defaults: { timezone: string; locale: string };
    timezones: string[];
}

function OperatorAvatar({ name, initials, avatar, className }: { name: string; initials: string; avatar: string | null; className?: string }) {
    return (
        <Avatar className={className}>
            {avatar && <AvatarImage src={avatar} alt={name} />}
            <AvatarFallback>{initials}</AvatarFallback>
        </Avatar>
    );
}

function SwitchField({ id, label, checked, onChange }: { id: string; label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <div className="flex items-center gap-3">
            <Switch id={id} checked={checked} onCheckedChange={onChange} />
            <Label htmlFor={id}>{label}</Label>
        </div>
    );
}

/** Managers upload for their staff: the person who set them up has the photo to hand. */
function MemberPhoto({ member }: { member: Operator }) {
    const { errors } = usePage().props;

    return (
        <FormField
            id="member-photo"
            label={__('Photo')}
            description={__('Shown to customers when they choose who to meet. JPG, PNG or WebP, up to 1 MB.')}
            error={errors.photo}
            className="sm:col-span-2"
        >
            <div className="mt-2 flex flex-wrap items-center gap-4">
                <OperatorAvatar name={member.name} initials={member.initials} avatar={member.avatar} className="size-16" />

                <div className="flex items-center gap-2">
                    <Input
                        id="member-photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="max-w-xs"
                        onChange={(event) => {
                            const file = event.target.files?.[0];
                            event.target.value = '';

                            if (file) {
                                router.post(photoRoutes.store.url(member.id), { photo: file }, { forceFormData: true, preserveScroll: true });
                            }
                        }}
                    />

                    {member.avatar && (
                        <Button type="button" size="sm" variant="ghost" onClick={() => router.delete(photoRoutes.destroy.url(member.id), { preserveScroll: true })}>
                            <Trash2 />
                            {__('Remove')}
                        </Button>
                    )}
                </div>
            </div>
        </FormField>
    );
}

function MemberDialog({ member, onClose, props }: { member: Operator | null; onClose: () => void; props: Props }) {
    const form = useForm({
        name: member?.name ?? '',
        email: member?.email ?? '',
        phone: member?.phone ?? '',
        job_title: member?.job_title ?? '',
        bio: member?.bio ?? '',
        timezone: member?.timezone ?? props.defaults.timezone,
        locale: member?.locale ?? props.defaults.locale,
        is_bookable: member?.is_bookable ?? true,
        is_active: member?.is_active ?? true,
        service_ids: member?.service_ids ?? [],
    });

    function save(event: FormEvent): void {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (member === null) {
            form.post(staffRoutes.store.url(), options);
        } else {
            form.put(staffRoutes.update.url(member.id), options);
        }
    }

    function toggleService(serviceId: number, checked: boolean): void {
        form.setData('service_ids', checked ? [...form.data.service_ids, serviceId] : form.data.service_ids.filter((id) => id !== serviceId));
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form onSubmit={save} className="space-y-6">
                    <DialogHeader>
                        <DialogTitle>{member ? __('Edit operator') : __('New operator')}</DialogTitle>
                        <DialogDescription>
                            {member ? __('Update the profile and the services this person delivers.') : __('A login will be created and the password shown once.')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {member && <MemberPhoto member={member} />}

                        <FormField id="member-name" label={__('Full name')} error={form.errors.name}>
                            <Input id="member-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required autoFocus />
                        </FormField>
                        <FormField id="member-email" label={__('Email')} error={form.errors.email}>
                            <Input id="member-email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
                        </FormField>
                        <FormField id="member-phone" label={__('Phone')} error={form.errors.phone}>
                            <Input id="member-phone" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                        </FormField>
                        <FormField id="member-job-title" label={__('Role shown to customers')} error={form.errors.job_title}>
                            <Input id="member-job-title" value={form.data.job_title} onChange={(event) => form.setData('job_title', event.target.value)} />
                        </FormField>
                    </div>

                    <FormField id="member-bio" label={__('Short bio')} error={form.errors.bio}>
                        <Textarea id="member-bio" rows={2} value={form.data.bio} onChange={(event) => form.setData('bio', event.target.value)} />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="member-timezone" label={__('Timezone')} error={form.errors.timezone}>
                            <Select value={form.data.timezone} onValueChange={(value) => form.setData('timezone', value)}>
                                <SelectTrigger id="member-timezone" className="w-full">
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
                        <FormField id="member-locale" label={__('Language')} error={form.errors.locale}>
                            <Select value={form.data.locale} onValueChange={(value) => form.setData('locale', value)}>
                                <SelectTrigger id="member-locale" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="it">Italiano</SelectItem>
                                    <SelectItem value="en">English</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>

                    {props.services.length > 0 && (
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">{__('Services delivered')}</legend>
                            {props.services.map((service) => (
                                <div key={service.id} className="flex items-center gap-3">
                                    <Checkbox
                                        id={`service-${service.id}`}
                                        checked={form.data.service_ids.includes(service.id)}
                                        onCheckedChange={(checked) => toggleService(service.id, checked === true)}
                                    />
                                    <Label htmlFor={`service-${service.id}`} className="font-normal">
                                        {service.name}
                                    </Label>
                                </div>
                            ))}
                            <InputError message={form.errors.service_ids} />
                        </fieldset>
                    )}

                    <div className="space-y-3">
                        <SwitchField id="member-bookable" label={__('Can receive bookings')} checked={form.data.is_bookable} onChange={(checked) => form.setData('is_bookable', checked)} />
                        <SwitchField id="member-active" label={__('Account enabled')} checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {member ? __('Save changes') : __('Create operator')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({ member, onClose }: { member: Operator | null; onClose: () => void }) {
    const blocked = (member?.upcoming_count ?? 0) > 0;

    return (
        <Dialog open={member !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Delete this operator?')}</DialogTitle>
                    <DialogDescription>{__('Their login, availability and service assignments will be removed.')}</DialogDescription>
                </DialogHeader>

                {member && blocked && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertDescription>
                            {trans_choice(
                                'This operator still has one upcoming booking. Cancel or reassign it first.|This operator still has :count upcoming bookings. Cancel or reassign them first.',
                                member.upcoming_count,
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {__('Cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={blocked}
                        onClick={() => member && router.delete(staffRoutes.destroy.url(member.id), { preserveScroll: true, onFinish: onClose })}
                    >
                        {__('Delete operator')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Status({ operator }: { operator: Operator }) {
    if (!operator.is_active) {
        return <ColorBadge color="zinc">{__('Disabled')}</ColorBadge>;
    }

    return operator.is_bookable ? <ColorBadge color="emerald">{__('Bookable')}</ColorBadge> : <ColorBadge color="amber">{__('Not bookable')}</ColorBadge>;
}

export default function Staff(props: Props) {
    const { operators, managerBookable } = props;
    const user = usePage().props.auth.user!;
    const [editing, setEditing] = useState<{ id: number | null } | null>(null);
    const [deleting, setDeleting] = useState<Operator | null>(null);

    // Read from the props on every render, so a new photo shows up at once.
    const editingMember = editing?.id ? (operators.find((operator) => operator.id === editing.id) ?? null) : null;

    const newOperator = (
        <Button onClick={() => setEditing({ id: null })}>
            <Plus />
            {__('New operator')}
        </Button>
    );

    return (
        <>
            <Head title={__('Staff')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Staff')} subheading={__('The people who deliver your services and receive bookings.')} actions={newOperator} />

                <div className="flex flex-col gap-4 rounded-xl border p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <OperatorAvatar name={user.name} initials={user.initials} avatar={user.avatar} />
                        <div>
                            <div className="font-medium">{user.name}</div>
                            <div className="text-sm text-muted-foreground">{__('You manage this portal')}</div>
                        </div>
                    </div>

                    <SwitchField
                        id="manager-bookable"
                        label={__('I also take bookings')}
                        checked={managerBookable}
                        onChange={() => router.patch(staffRoutes.bookable.url(), {}, { preserveScroll: true })}
                    />
                </div>

                {operators.length === 0 ? (
                    <EmptyState icon={UsersRound} title={__('No operators yet')}>
                        <p className="max-w-md">{__('Add the people working with you. Each one gets their own login and availability.')}</p>
                        <div className="mt-4">{newOperator}</div>
                    </EmptyState>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-4">{__('Operator')}</TableHead>
                                    <TableHead>{__('Services')}</TableHead>
                                    <TableHead>{__('Weekly slots')}</TableHead>
                                    <TableHead>{__('Status')}</TableHead>
                                    <TableHead className="pe-4" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {operators.map((operator) => (
                                    <TableRow key={operator.id}>
                                        <TableCell className="ps-4">
                                            <div className="flex items-center gap-3">
                                                <OperatorAvatar name={operator.name} initials={operator.initials} avatar={operator.avatar} className="size-7" />
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">{operator.name}</div>
                                                    <div className="truncate text-sm text-muted-foreground">{operator.job_title || operator.email}</div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {operator.service_names.length === 0 ? (
                                                <ColorBadge color="amber">{__('None')}</ColorBadge>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {operator.service_names.slice(0, 3).map((name) => (
                                                        <ColorBadge key={name} color="zinc">
                                                            {name}
                                                        </ColorBadge>
                                                    ))}
                                                    {operator.service_names.length > 3 && <ColorBadge color="zinc">+{operator.service_names.length - 3}</ColorBadge>}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="tabular-nums">{operator.weekly_slots}</TableCell>
                                        <TableCell>
                                            <Status operator={operator} />
                                        </TableCell>
                                        <TableCell className="pe-4 text-end">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button size="icon" variant="ghost" aria-label={__('Actions')}>
                                                        <Ellipsis />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onSelect={() => setEditing({ id: operator.id })}>
                                                        <Pencil />
                                                        {__('Edit')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem variant="destructive" onSelect={() => setDeleting(operator)}>
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
            </div>

            {editing && <MemberDialog key={editing.id ?? 'new'} member={editingMember} onClose={() => setEditing(null)} props={props} />}
            <CredentialsDialog title={__('Operator created')} description={__('Share these credentials. The password is shown only once.')} />
            <DeleteDialog member={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}
