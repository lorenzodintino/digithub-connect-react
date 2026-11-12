import { Head, router, useForm } from '@inertiajs/react';
import { Ellipsis, Eye, EyeOff, Info, Layers, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __ } from '@/lib/i18n';
import * as planRoutes from '@/routes/admin/plans';

type PlanForm = {
    name: string;
    description: string;
    price: string;
    max_services: string;
    max_operators: string;
    max_bookings_per_month: string;
    allows_custom_smtp: boolean;
    is_active: boolean;
    position: string;
};

interface Plan {
    id: number;
    name: string;
    description: string | null;
    price: string | null;
    services: string;
    operators: string;
    bookings: string;
    allows_custom_smtp: boolean;
    is_active: boolean;
    portals_count: number;
    can_delete: boolean;
    form: PlanForm;
}

const blank: PlanForm = {
    name: '',
    description: '',
    price: '',
    max_services: '',
    max_operators: '',
    max_bookings_per_month: '',
    allows_custom_smtp: false,
    is_active: true,
    position: '0',
};

function PlanSheet({ plan, onClose }: { plan: Plan | null; onClose: () => void }) {
    const form = useForm(plan?.form ?? blank);

    function save(event: FormEvent): void {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (plan === null) {
            form.post(planRoutes.store.url(), options);
        } else {
            form.put(planRoutes.update.url(plan.id), options);
        }
    }

    function number(key: 'price' | 'position' | 'max_services' | 'max_operators' | 'max_bookings_per_month', label: string, extra: { min: number; step?: string; description?: string }) {
        return (
            <FormField id={`plan-${key}`} label={label} description={extra.description} error={form.errors[key]}>
                <Input id={`plan-${key}`} type="number" min={extra.min} step={extra.step} value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} />
            </FormField>
        );
    }

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                <form onSubmit={save} className="space-y-6 p-6">
                    <SheetHeader className="p-0 pe-8">
                        <SheetTitle className="text-lg">{plan ? __('Edit plan') : __('New plan')}</SheetTitle>
                        <SheetDescription>{__('Leave a limit empty to grant it without a cap.')}</SheetDescription>
                    </SheetHeader>

                    <FormField id="plan-name" label={__('Plan name')} error={form.errors.name}>
                        <Input id="plan-name" required autoFocus value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </FormField>
                    <FormField id="plan-description" label={__('Description')} error={form.errors.description}>
                        <Input id="plan-description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {number('price', __('Monthly price'), { min: 0, step: '0.01', description: __('Indicative: the payment is recorded by hand on each portal.') })}
                        {number('position', __('Order'), { min: 0 })}
                        {number('max_services', __('Services limit'), { min: 1 })}
                        {number('max_operators', __('Operators limit'), { min: 1 })}
                        {number('max_bookings_per_month', __('Bookings per month'), { min: 1 })}
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <Switch id="plan-smtp" checked={form.data.allows_custom_smtp} onCheckedChange={(checked) => form.setData('allows_custom_smtp', checked)} />
                            <Label htmlFor="plan-smtp">{__('Can send from its own SMTP server')}</Label>
                        </div>
                        <div className="flex items-center gap-3">
                            <Switch id="plan-active" checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} />
                            <Label htmlFor="plan-active">{__('On sale')}</Label>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {plan ? __('Save changes') : __('Create plan')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function DeleteDialog({ plan, onClose }: { plan: Plan | null; onClose: () => void }) {
    return (
        <Dialog open={plan !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Delete this plan?')}</DialogTitle>
                    <DialogDescription>{__('Only a plan with no portal on it can be deleted. Retire it instead to stop selling it.')}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {__('Cancel')}
                    </Button>
                    <Button variant="destructive" onClick={() => plan && router.delete(planRoutes.destroy.url(plan.id), { preserveScroll: true, onSuccess: onClose })}>
                        {__('Delete plan')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Plans({ plans }: { plans: Plan[] }) {
    const [editing, setEditing] = useState<Plan | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Plan | null>(null);

    return (
        <>
            <Head title={__('Plans')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader
                    heading={__('Plans')}
                    subheading={__('What each portal is entitled to.')}
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            {__('New plan')}
                        </Button>
                    }
                />

                <Alert>
                    <Info />
                    <AlertDescription>
                        {__(
                            'Services and operators past the limit are refused. The monthly bookings are a threshold: past it customers still book, and the manager is warned. A portal without its own SMTP in the plan sends from the platform. Payments are recorded by hand on each portal.',
                        )}
                    </AlertDescription>
                </Alert>

                {plans.length === 0 ? (
                    <EmptyState icon={Layers} title={__('No plan yet')}>
                        <p>{__('Create the first plan to describe what you sell.')}</p>
                        <Button className="mt-4" onClick={() => setEditing('new')}>
                            <Plus />
                            {__('New plan')}
                        </Button>
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
                                    <TableHead>{__('Own SMTP')}</TableHead>
                                    <TableHead>{__('Portals')}</TableHead>
                                    <TableHead className="pe-4" />
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
                                        <TableCell>
                                            <ColorBadge color={plan.allows_custom_smtp ? 'emerald' : 'zinc'}>{plan.allows_custom_smtp ? __('Included') : __('No')}</ColorBadge>
                                        </TableCell>
                                        <TableCell className="tabular-nums">{plan.portals_count}</TableCell>
                                        <TableCell className="pe-4 text-end">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button size="icon" variant="ghost" aria-label={__('Plan actions')}>
                                                        <Ellipsis />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onSelect={() => setEditing(plan)}>
                                                        <Pencil />
                                                        {__('Edit')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onSelect={() => router.patch(planRoutes.toggle.url(plan.id), {}, { preserveScroll: true })}>
                                                        {plan.is_active ? <EyeOff /> : <Eye />}
                                                        {plan.is_active ? __('Retire') : __('Put on sale')}
                                                    </DropdownMenuItem>
                                                    {plan.can_delete && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem variant="destructive" onSelect={() => setDeleting(plan)}>
                                                                <Trash2 />
                                                                {__('Delete')}
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
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

            {editing !== null && <PlanSheet key={editing === 'new' ? 'new' : editing.id} plan={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
            <DeleteDialog plan={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}
