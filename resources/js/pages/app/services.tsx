import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Clock, Ellipsis, ExternalLink, Eye, EyeOff, LayoutGrid, Link2, Lock, MapPin, Pencil, Phone, Plus, Trash2, TriangleAlert, Video } from 'lucide-react';
import { type DragEvent, type FormEvent, type ReactNode, useEffect, useRef, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { EmptyState } from '@/components/empty-state';
import { FormField, InputError } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { ServiceHelp } from '@/components/service-help';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { UpgradeRequest, type UpgradeReason } from '@/components/upgrade-request';
import { __, trans_choice } from '@/lib/i18n';
import { resources as resourcesRoute, services as servicesRoute } from '@/routes/app';
import * as serviceRoutes from '@/routes/app/services';

interface ServiceCard {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    color: string;
    duration: string;
    location_type: string;
    location_label: string;
    price: string | null;
    requires_confirmation: boolean;
    is_active: boolean;
    operators: { id: number; name: string; initials: string; avatar: string | null }[];
    has_operators: boolean;
    public_url: string;
    upcoming_count: number;
}

/** App\Support\ServiceFormData */
interface ServiceForm {
    name: string;
    description: string;
    duration_minutes: number;
    capacity: number;
    is_package: boolean;
    use_component_prices: boolean;
    package_items: { service_id: number | ''; pause_after_minutes: number }[];
    buffer_before_minutes: number;
    buffer_after_minutes: number;
    slot_interval_minutes: number;
    min_notice_minutes: number;
    max_advance_days: number;
    cancellation_notice_hours: number;
    price: string;
    use_brand_color: boolean;
    color: string;
    location_type: string;
    location_value: string;
    requires_confirmation: boolean;
    is_active: boolean;
    operator_ids: number[];
    resource_requirements: { name: string; resource_ids: number[] }[];
    questions: { id: number | null; label: string; type: string; options: string; is_required: boolean }[];
}

interface Props {
    services: ServiceCard[];
    editing: { slug: string; id: number; form: ServiceForm } | null;
    defaults: ServiceForm;
    staff: { id: number; name: string }[];
    resources: { id: number; name: string; is_active: boolean }[];
    /** The plan does not include resources: the requirements a service has stay as they are. */
    resourcesLocked: boolean;
    packageServices: { id: number; name: string; duration: string }[];
    locationTypes: { value: string; label: string; placeholder: string }[];
    questionTypes: { value: string; label: string; needs_options: boolean }[];
    limits: { questions: number; requirements: number };
}

const locationIcons: Record<string, typeof MapPin> = { in_person: MapPin, phone: Phone, video: Video, custom: Link2 };

function Divider({ label }: { label: string }) {
    return (
        <div className="flex items-center gap-3">
            <Separator className="flex-1" />
            <span className="text-sm text-muted-foreground">{label}</span>
            <Separator className="flex-1" />
        </div>
    );
}

function SwitchField({ id, label, description, checked, disabled, onChange }: { id: string; label: string; description?: string; checked: boolean; disabled?: boolean; onChange: (checked: boolean) => void }) {
    return (
        <div className="flex items-start gap-3">
            <Switch id={id} checked={checked} disabled={disabled} onCheckedChange={onChange} className="mt-0.5" />
            <div className="grid gap-1">
                <Label htmlFor={id}>{label}</Label>
                {description && <p className="text-sm text-muted-foreground">{description}</p>}
            </div>
        </div>
    );
}

/** A number field that sends null when emptied, so the server says what is missing. */
function NumberField({
    id,
    label,
    value,
    onChange,
    error,
    description,
    min,
    step,
    max,
    required,
}: {
    id: string;
    label: string;
    value: number;
    onChange: (value: number) => void;
    error?: string;
    description?: string;
    min?: number;
    step?: number;
    max?: number;
    required?: boolean;
}) {
    return (
        <FormField id={id} label={label} description={description} error={error}>
            <Input
                id={id}
                type="number"
                min={min}
                max={max}
                step={step}
                required={required}
                value={Number.isNaN(value) ? '' : value}
                onChange={(event) => onChange(event.target.valueAsNumber)}
            />
        </FormField>
    );
}

function CheckboxList({ name, options, selected, onChange }: { name: string; options: { id: number; label: string }[]; selected: number[]; onChange: (ids: number[]) => void }) {
    return (
        <div className="space-y-2">
            {options.map((option) => (
                <div key={option.id} className="flex items-center gap-3">
                    <Checkbox
                        id={`${name}-${option.id}`}
                        checked={selected.includes(option.id)}
                        onCheckedChange={(checked) => onChange(checked === true ? [...selected, option.id] : selected.filter((id) => id !== option.id))}
                    />
                    <Label htmlFor={`${name}-${option.id}`} className="font-normal">
                        {option.label}
                    </Label>
                </div>
            ))}
        </div>
    );
}

function ServiceFormSheet({ props, editing, onClose }: { props: Props; editing: Props['editing']; onClose: () => void }) {
    const form = useForm<ServiceForm>(editing?.form ?? props.defaults);
    const data = form.data;
    const errors = form.errors as Record<string, string | undefined>;

    function set<K extends keyof ServiceForm>(key: K, value: ServiceForm[K]): void {
        form.setData((current) => ({ ...current, [key]: value }));
    }

    function togglePackage(isPackage: boolean): void {
        form.setData({
            ...data,
            is_package: isPackage,
            resource_requirements: [],
            package_items: isPackage
                ? [
                      { service_id: '', pause_after_minutes: 0 },
                      { service_id: '', pause_after_minutes: 0 },
                  ]
                : [],
            capacity: isPackage ? 1 : data.capacity,
        });
    }

    function updateItem(index: number, changes: Partial<ServiceForm['package_items'][number]>): void {
        set(
            'package_items',
            data.package_items.map((item, position) => (position === index ? { ...item, ...changes } : item)),
        );
    }

    function moveItem(index: number, direction: -1 | 1): void {
        const items = [...data.package_items];
        [items[index], items[index + direction]] = [items[index + direction], items[index]];
        set('package_items', items);
    }

    function updateRequirement(index: number, changes: Partial<ServiceForm['resource_requirements'][number]>): void {
        set(
            'resource_requirements',
            data.resource_requirements.map((requirement, position) => (position === index ? { ...requirement, ...changes } : requirement)),
        );
    }

    function updateQuestion(index: number, changes: Partial<ServiceForm['questions'][number]>): void {
        set(
            'questions',
            data.questions.map((question, position) => (position === index ? { ...question, ...changes } : question)),
        );
    }

    function save(event: FormEvent): void {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (editing) {
            form.put(serviceRoutes.update.url(editing.slug), options);
        } else {
            form.post(serviceRoutes.store.url(), options);
        }
    }

    const packageServices = props.packageServices.filter((service) => service.id !== editing?.id);
    const placeholder = props.locationTypes.find((type) => type.value === data.location_type)?.placeholder;
    const videoCall = data.location_type === 'video';

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                <form onSubmit={save} className="space-y-6 p-6">
                    <div className="flex items-start justify-between gap-3 pe-8">
                        <SheetHeader className="p-0">
                            <SheetTitle className="text-lg">{editing ? __('Edit service') : __('New service')}</SheetTitle>
                            <SheetDescription>{__('Duration, buffers and booking rules define how slots are generated.')}</SheetDescription>
                        </SheetHeader>
                        <ServiceHelp size="sm" />
                    </div>

                    <FormField id="service-name" label={__('Service name')} error={errors.name}>
                        <Input id="service-name" value={data.name} onChange={(event) => set('name', event.target.value)} required autoFocus />
                    </FormField>
                    {errors.plan_limit && <UpgradeRequest reason={errors.plan_limit as UpgradeReason} />}

                    <FormField id="service-description" label={__('Description')} error={errors.description}>
                        <Textarea id="service-description" rows={3} value={data.description} onChange={(event) => set('description', event.target.value)} />
                    </FormField>

                    <div className="space-y-1">
                        <SwitchField
                            id="service-package"
                            label={__('Service package')}
                            description={__('Several services in order, with one operator and one booking.')}
                            checked={data.is_package}
                            onChange={togglePackage}
                        />
                        <InputError message={errors.is_package} />
                    </div>

                    {data.is_package && (
                        <div className="space-y-4">
                            <h3 className="font-medium">{__('Package phases')}</h3>
                            <p className="text-sm text-muted-foreground">{__('Choose at least two different individual services. Cabins may change between phases.')}</p>

                            {data.package_items.map((item, index) => (
                                <div key={index} className="space-y-3 rounded-xl border p-4">
                                    <FormField id={`phase-${index}`} label={__('Service')} error={errors[`package_items.${index}.service_id`]}>
                                        <Select value={item.service_id === '' ? '' : String(item.service_id)} onValueChange={(value) => updateItem(index, { service_id: Number(value) })}>
                                            <SelectTrigger id={`phase-${index}`} className="w-full">
                                                <SelectValue placeholder={__('Choose a service')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {packageServices.map((service) => (
                                                    <SelectItem key={service.id} value={String(service.id)}>
                                                        {service.name} · {service.duration}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>

                                    {index < data.package_items.length - 1 && (
                                        <NumberField
                                            id={`phase-pause-${index}`}
                                            label={__('Pause after this service (minutes)')}
                                            min={0}
                                            max={240}
                                            value={item.pause_after_minutes}
                                            onChange={(value) => updateItem(index, { pause_after_minutes: value })}
                                            error={errors[`package_items.${index}.pause_after_minutes`]}
                                        />
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        <Button type="button" size="sm" variant="outline" disabled={index === 0} onClick={() => moveItem(index, -1)}>
                                            <ArrowUp />
                                            {__('Move up')}
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" disabled={index === data.package_items.length - 1} onClick={() => moveItem(index, 1)}>
                                            <ArrowDown />
                                            {__('Move down')}
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => set('package_items', data.package_items.filter((_, position) => position !== index))}
                                        >
                                            <Trash2 />
                                            {__('Remove')}
                                        </Button>
                                    </div>
                                </div>
                            ))}

                            <InputError message={errors.package_items} />

                            {data.package_items.length < 10 && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => set('package_items', [...data.package_items, { service_id: '', pause_after_minutes: 0 }])}
                                >
                                    <Plus />
                                    {__('Add service to package')}
                                </Button>
                            )}

                            <SwitchField
                                id="service-component-prices"
                                label={__('Sum component prices')}
                                description={__('Turn off to set a price for the whole package.')}
                                checked={data.use_component_prices}
                                onChange={(checked) => set('use_component_prices', checked)}
                            />

                            <p className="text-sm text-muted-foreground">
                                {__('Duration includes all services and pauses. Booking rules combine the restrictions of each service. Questions and resources come from each phase.')}
                            </p>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        {!data.is_package && (
                            <NumberField
                                id="service-duration"
                                label={__('Duration (minutes)')}
                                min={5}
                                step={5}
                                required
                                value={data.duration_minutes}
                                onChange={(value) => set('duration_minutes', value)}
                                error={errors.duration_minutes}
                            />
                        )}
                        <NumberField
                            id="service-interval"
                            label={__('Slot interval (minutes)')}
                            min={5}
                            step={5}
                            required
                            value={data.slot_interval_minutes}
                            onChange={(value) => set('slot_interval_minutes', value)}
                            error={errors.slot_interval_minutes}
                        />
                        {!data.is_package && (
                            <NumberField
                                id="service-capacity"
                                label={__('Places per appointment')}
                                description={__('1 for a one-to-one service. More turns it into a group one: several people share the same time.')}
                                min={1}
                                value={data.capacity}
                                onChange={(value) => set('capacity', value)}
                                error={errors.capacity}
                            />
                        )}
                        <NumberField
                            id="service-buffer-before"
                            label={__('Buffer before (minutes)')}
                            min={0}
                            step={5}
                            value={data.buffer_before_minutes}
                            onChange={(value) => set('buffer_before_minutes', value)}
                            error={errors.buffer_before_minutes}
                        />
                        <NumberField
                            id="service-buffer-after"
                            label={__('Buffer after (minutes)')}
                            min={0}
                            step={5}
                            value={data.buffer_after_minutes}
                            onChange={(value) => set('buffer_after_minutes', value)}
                            error={errors.buffer_after_minutes}
                        />
                        <NumberField
                            id="service-notice"
                            label={__('Minimum notice (minutes)')}
                            min={0}
                            step={15}
                            value={data.min_notice_minutes}
                            onChange={(value) => set('min_notice_minutes', value)}
                            error={errors.min_notice_minutes}
                        />
                        <NumberField
                            id="service-advance"
                            label={__('Bookable up to (days)')}
                            min={1}
                            value={data.max_advance_days}
                            onChange={(value) => set('max_advance_days', value)}
                            error={errors.max_advance_days}
                        />
                        <NumberField
                            id="service-cancellation"
                            label={__('Cancellation notice (hours)')}
                            description={__('The customer can cancel or move up to this many hours before. 0 lets them do it any time.')}
                            min={0}
                            value={data.cancellation_notice_hours}
                            onChange={(value) => set('cancellation_notice_hours', value)}
                            error={errors.cancellation_notice_hours}
                        />
                        {(!data.is_package || !data.use_component_prices) && (
                            <FormField id="service-price" label={__('Price')} description={__('Leave empty for a free service.')} error={errors.price}>
                                <Input id="service-price" type="number" min={0} step={0.01} value={data.price} onChange={(event) => set('price', event.target.value)} />
                            </FormField>
                        )}

                        <div className="space-y-3">
                            <SwitchField
                                id="service-brand-color"
                                label={__('Use the portal brand color')}
                                description={__('Turn off to give this service its own color.')}
                                checked={data.use_brand_color}
                                onChange={(checked) => set('use_brand_color', checked)}
                            />
                            {!data.use_brand_color && (
                                <FormField id="service-color" label={__('Color')} error={errors.color}>
                                    <Input id="service-color" type="color" value={data.color} onChange={(event) => set('color', event.target.value)} className="h-10 w-20 p-1" />
                                </FormField>
                            )}
                        </div>
                    </div>

                    <Divider label={__('Where it takes place')} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="service-location-type" label={__('Location type')} error={errors.location_type}>
                            <Select value={data.location_type} onValueChange={(value) => set('location_type', value)}>
                                <SelectTrigger id="service-location-type" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {props.locationTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        {!videoCall && (
                            <FormField id="service-location-value" label={__('Location details')} error={errors.location_value}>
                                <Input id="service-location-value" value={data.location_value} placeholder={placeholder} onChange={(event) => set('location_value', event.target.value)} />
                            </FormField>
                        )}
                    </div>

                    <Divider label={__('Who delivers it')} />

                    {props.staff.length === 0 ? (
                        <Alert>
                            <TriangleAlert />
                            <AlertDescription>{__('No bookable people yet. Add operators from the Staff page first.')}</AlertDescription>
                        </Alert>
                    ) : (
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">{__('Operators')}</legend>
                            <CheckboxList
                                name="operator"
                                options={props.staff.map((member) => ({ id: member.id, label: member.name }))}
                                selected={data.operator_ids}
                                onChange={(ids) => set('operator_ids', ids)}
                            />
                            <InputError message={errors.operator_ids} />
                        </fieldset>
                    )}

                    {!data.is_package && (
                        <>
                            <Divider label={__('Resources needed')} />

                            <div className="space-y-4">
                                <Alert>
                                    <TriangleAlert />
                                    <AlertDescription>
                                        {__('Booking times require a free operator and all the resources needed by the service. Preparation, cleanup and unavailability are included.')}
                                    </AlertDescription>
                                </Alert>
                                <p className="text-sm text-muted-foreground">
                                    {__('Add a requirement for each resource needed together. Within a requirement, select interchangeable alternatives: one will be assigned.')}
                                </p>

                                {props.resourcesLocked ? (
                                    <>
                                        <Alert data-test="requirements-locked">
                                            <Lock />
                                            <AlertDescription>{__('Resources are not included in your plan, so the requirements of a service cannot be changed. Those already set keep holding the agenda.')}</AlertDescription>
                                        </Alert>
                                        {data.resource_requirements.map((requirement, index) => (
                                            <div key={index} className="rounded-xl border p-4">
                                                <p className="font-medium">{requirement.name}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {props.resources
                                                        .filter((resource) => requirement.resource_ids.includes(resource.id))
                                                        .map((resource) => resource.name)
                                                        .join(', ')}
                                                </p>
                                            </div>
                                        ))}
                                    </>
                                ) : props.resources.length === 0 ? (
                                    <>
                                        <p className="text-sm">{__('Create resources before assigning them to a service.')}</p>
                                        <Button type="button" variant="outline" asChild>
                                            <Link href={resourcesRoute()}>{__('Manage resources')}</Link>
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        {data.resource_requirements.map((requirement, index) => (
                                            <div key={index} className="space-y-3 rounded-xl border p-4">
                                                <FormField id={`requirement-${index}`} label={__('Requirement name')} error={errors[`resource_requirements.${index}.name`]}>
                                                    <Input
                                                        id={`requirement-${index}`}
                                                        value={requirement.name}
                                                        placeholder={__('e.g. Cabin or laser machine')}
                                                        onChange={(event) => updateRequirement(index, { name: event.target.value })}
                                                    />
                                                </FormField>

                                                <fieldset className="space-y-2">
                                                    <legend className="text-sm font-medium">{__('Resource alternatives')}</legend>
                                                    <CheckboxList
                                                        name={`requirement-${index}-resource`}
                                                        options={props.resources.map((resource) => ({
                                                            id: resource.id,
                                                            label: resource.is_active ? resource.name : `${resource.name} (${__('Inactive')})`,
                                                        }))}
                                                        selected={requirement.resource_ids}
                                                        onChange={(ids) => updateRequirement(index, { resource_ids: ids })}
                                                    />
                                                    <InputError message={errors[`resource_requirements.${index}.resource_ids`]} />
                                                    {requirement.resource_ids.map((_, choice) => (
                                                        <InputError key={choice} message={errors[`resource_requirements.${index}.resource_ids.${choice}`]} />
                                                    ))}
                                                </fieldset>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => set('resource_requirements', data.resource_requirements.filter((_, position) => position !== index))}
                                                >
                                                    {__('Remove requirement')}
                                                </Button>
                                            </div>
                                        ))}

                                        <InputError message={errors.resource_requirements} />

                                        {data.resource_requirements.length < props.limits.requirements && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => set('resource_requirements', [...data.resource_requirements, { name: '', resource_ids: [] }])}
                                            >
                                                <Plus />
                                                {__('Add resource requirement')}
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>
                        </>
                    )}

                    <Divider label={__('What to ask the customer')} />

                    <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">{__('Beyond name, email and phone. Answers arrive with the booking and stay on it.')}</p>

                        {data.questions.map((question, index) => {
                            const needsOptions = props.questionTypes.find((type) => type.value === question.type)?.needs_options ?? false;

                            return (
                                <div key={question.id ?? `new-${index}`} className="space-y-3 rounded-xl border p-4">
                                    <div className="flex items-end gap-2">
                                        <FormField id={`question-${index}`} label={__('Question')} className="flex-1">
                                            <Input
                                                id={`question-${index}`}
                                                value={question.label}
                                                placeholder={__('First visit? VAT number? …')}
                                                onChange={(event) => updateQuestion(index, { label: event.target.value })}
                                            />
                                        </FormField>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            title={__('Remove')}
                                            aria-label={__('Remove question')}
                                            onClick={() => set('questions', data.questions.filter((_, position) => position !== index))}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                    <InputError message={errors[`questions.${index}.label`]} />

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <FormField id={`question-type-${index}`} label={__('Answer type')}>
                                            <Select value={question.type} onValueChange={(value) => updateQuestion(index, { type: value })}>
                                                <SelectTrigger id={`question-type-${index}`} className="w-full">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {props.questionTypes.map((type) => (
                                                        <SelectItem key={type.value} value={type.value}>
                                                            {type.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </FormField>

                                        <div className="flex items-end pb-2">
                                            <SwitchField
                                                id={`question-required-${index}`}
                                                label={__('Answer required')}
                                                checked={question.is_required}
                                                onChange={(checked) => updateQuestion(index, { is_required: checked })}
                                            />
                                        </div>
                                    </div>

                                    {needsOptions && (
                                        <FormField id={`question-options-${index}`} label={__('Choices')} description={__('One per line.')} error={errors[`questions.${index}.options`]}>
                                            <Textarea id={`question-options-${index}`} rows={3} value={question.options} onChange={(event) => updateQuestion(index, { options: event.target.value })} />
                                        </FormField>
                                    )}
                                </div>
                            );
                        })}

                        <InputError message={errors.questions} />

                        {data.questions.length < props.limits.questions ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => set('questions', [...data.questions, { id: null, label: '', type: props.questionTypes[0].value, options: '', is_required: false }])}
                            >
                                <Plus />
                                {__('Add question')}
                            </Button>
                        ) : (
                            <p className="text-sm text-muted-foreground">{__('Ten questions is the most a booking form should ask.')}</p>
                        )}
                    </div>

                    <div className="space-y-3">
                        <SwitchField
                            id="service-confirmation"
                            label={__('Bookings need manual approval')}
                            checked={videoCall || data.requires_confirmation}
                            disabled={videoCall}
                            description={videoCall ? __('Add the video call link when you confirm the booking.') : undefined}
                            onChange={(checked) => set('requires_confirmation', checked)}
                        />
                        <SwitchField id="service-active" label={__('Published on the public page')} checked={data.is_active} onChange={(checked) => set('is_active', checked)} />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {editing ? __('Save changes') : __('Create service')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function DeleteDialog({ service, onClose }: { service: ServiceCard | null; onClose: () => void }) {
    const [error, setError] = useState<string | undefined>();
    const blocked = (service?.upcoming_count ?? 0) > 0;

    function close(): void {
        setError(undefined);
        onClose();
    }

    function remove(): void {
        if (service) {
            router.delete(serviceRoutes.destroy.url(service.slug), {
                preserveScroll: true,
                onSuccess: close,
                onError: (errors) => setError(errors.service),
            });
        }
    }

    return (
        <Dialog open={service !== null} onOpenChange={(open) => !open && close()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Delete this service?')}</DialogTitle>
                    <DialogDescription>{__('It will disappear from your public page immediately.')}</DialogDescription>
                </DialogHeader>

                {service && blocked && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertDescription>
                            {trans_choice(
                                'This service still has one upcoming booking. Cancel it first.|This service still has :count upcoming bookings. Cancel them first.',
                                service.upcoming_count,
                            )}
                        </AlertDescription>
                    </Alert>
                )}
                <InputError message={error} />

                <DialogFooter>
                    <Button variant="outline" onClick={close}>
                        {__('Cancel')}
                    </Button>
                    <Button variant="destructive" disabled={blocked} onClick={remove}>
                        {__('Delete service')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Card({ service, onEdit, onDelete, dragHandlers }: { service: ServiceCard; onEdit: () => void; onDelete: () => void; dragHandlers: Record<string, unknown> }) {
    const LocationIcon = locationIcons[service.location_type] ?? MapPin;

    return (
        <div
            className="group relative flex cursor-move flex-col overflow-hidden rounded-xl border bg-card transition hover:shadow-md"
            draggable
            {...dragHandlers}
        >
            <div className="flex flex-1 flex-col gap-4 p-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h2 className="flex items-center gap-2 text-lg font-bold tracking-tight">
                            <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: service.color }} />
                            <span className="truncate">{service.name}</span>
                        </h2>
                        <p className="truncate text-sm text-muted-foreground">/{service.slug}</p>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button size="icon" variant="ghost" aria-label={__('Service actions')}>
                                <Ellipsis />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onSelect={onEdit}>
                                <Pencil />
                                {__('Edit')}
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <a href={service.public_url} target="_blank" rel="noopener">
                                    <ExternalLink />
                                    {__('Preview')}
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => router.patch(serviceRoutes.toggle.url(service.slug), {}, { preserveScroll: true })}>
                                {service.is_active ? <EyeOff /> : <Eye />}
                                {service.is_active ? __('Hide') : __('Publish')}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                                <Trash2 />
                                {__('Delete')}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {service.description && <p className="line-clamp-2 text-sm text-muted-foreground">{service.description}</p>}

                <div className="flex flex-wrap items-center gap-2">
                    <ColorBadge color="zinc">
                        <Clock />
                        {service.duration}
                    </ColorBadge>
                    <ColorBadge color="zinc">
                        <LocationIcon />
                        {service.location_label}
                    </ColorBadge>
                    {service.price && <ColorBadge color="zinc">{service.price}</ColorBadge>}
                    {service.requires_confirmation && <ColorBadge color="amber">{__('Needs approval')}</ColorBadge>}
                </div>

                <div className="mt-auto flex items-center justify-between gap-3 border-t pt-4">
                    {service.has_operators ? (
                        <div className="flex -space-x-1">
                            {service.operators.map((operator) => (
                                <Avatar key={operator.id} className="size-7 ring-2 ring-card" title={operator.name}>
                                    {operator.avatar && <AvatarImage src={operator.avatar} alt={operator.name} />}
                                    <AvatarFallback className="text-[10px] font-semibold">{operator.initials}</AvatarFallback>
                                </Avatar>
                            ))}
                        </div>
                    ) : (
                        <ColorBadge color="rose">{__('No operator assigned')}</ColorBadge>
                    )}

                    <ColorBadge color={service.is_active ? 'emerald' : 'zinc'}>{service.is_active ? __('Published') : __('Hidden')}</ColorBadge>
                </div>
            </div>
        </div>
    );
}

export default function Services(props: Props) {
    const { services, editing } = props;
    const [order, setOrder] = useState(services);
    const [sheet, setSheet] = useState<'new' | 'edit' | null>(editing ? 'edit' : null);
    const [deleting, setDeleting] = useState<ServiceCard | null>(null);
    const dragged = useRef<number | null>(null);

    useEffect(() => setOrder(services), [services]);

    function openEdit(slug: string): void {
        router.get(servicesRoute.url(), { edit: slug }, { only: ['editing'], preserveState: true, preserveScroll: true, replace: true, onSuccess: () => setSheet('edit') });
    }

    function closeSheet(): void {
        setSheet(null);

        if (editing) {
            router.get(servicesRoute.url(), {}, { only: ['editing'], preserveState: true, preserveScroll: true, replace: true });
        }
    }

    function drop(event: DragEvent, target: number): void {
        event.preventDefault();

        const from = dragged.current;
        dragged.current = null;

        if (from === null || from === target) {
            return;
        }

        const moved = order[from];
        const next = order.filter((_, index) => index !== from);
        next.splice(target, 0, moved);
        setOrder(next);

        router.patch(serviceRoutes.reorder.url(moved.slug), { position: target }, { preserveScroll: true });
    }

    const actions: ReactNode = (
        <>
            <ServiceHelp />
            <Button onClick={() => setSheet('new')}>
                <Plus />
                {__('New service')}
            </Button>
        </>
    );

    return (
        <>
            <Head title={__('Services')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Services')} subheading={__('What your customers can book, how long it takes and who delivers it.')} actions={actions} />

                {order.length === 0 ? (
                    <EmptyState icon={LayoutGrid} title={__('No services yet')}>
                        <p className="max-w-md">{__('Create your first service to start receiving bookings on your public page.')}</p>
                        <Button className="mt-4" onClick={() => setSheet('new')}>
                            <Plus />
                            {__('New service')}
                        </Button>
                    </EmptyState>
                ) : (
                    <>
                        <p className="text-sm text-muted-foreground">{__('Drag a card to change the order customers see on your page.')}</p>

                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {order.map((service, index) => (
                                <Card
                                    key={service.id}
                                    service={service}
                                    onEdit={() => openEdit(service.slug)}
                                    onDelete={() => setDeleting(service)}
                                    dragHandlers={{
                                        onDragStart: () => (dragged.current = index),
                                        onDragOver: (event: DragEvent) => event.preventDefault(),
                                        onDrop: (event: DragEvent) => drop(event, index),
                                    }}
                                />
                            ))}
                        </div>
                    </>
                )}
            </div>

            {sheet === 'new' && <ServiceFormSheet key="new" props={props} editing={null} onClose={closeSheet} />}
            {sheet === 'edit' && editing && <ServiceFormSheet key={editing.slug} props={props} editing={editing} onClose={closeSheet} />}

            <DeleteDialog service={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}
