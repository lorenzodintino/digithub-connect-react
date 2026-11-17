import { router, useForm, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { upgrade } from '@/routes/app/plan';
import { cancel } from '@/routes/app/plan/upgrade';

/** Why the manager is asking: it tells the administrator what to look at (see RequestPlanUpgrade::reasons). */
export type UpgradeReason = 'services' | 'operators' | 'bookings' | 'resources' | 'groups' | 'packages' | 'waiting_list' | 'embed' | 'calendar' | 'smtp' | 'other';

/**
 * "Ask for a bigger plan": a button and the small form behind it. It shows only to a manager whose plan has a plan
 * above it, so it can sit anywhere a limit is met without any check of its own.
 */
export function UpgradeRequest({ reason = 'other', className }: { reason?: UpgradeReason; className?: string }) {
    const plan = usePage().props.auth.user?.tenant?.plan ?? null;
    const [open, setOpen] = useState(false);
    const form = useForm({ plan_id: plan?.upgrades[0]?.id ?? 0, reason, note: '' });

    if (plan === null || plan.upgrades.length === 0) {
        return null;
    }

    // One request at a time: while one waits, say where it stands instead of asking again.
    if (plan.openRequest !== null) {
        const waiting = plan.openRequest;

        return (
            <span className={cn('inline-flex flex-wrap items-center gap-2 text-sm', className)} data-test="upgrade-open">
                <ColorBadge color={waiting.color}>{waiting.label}</ColorBadge>
                <span className="text-muted-foreground">{__('Your request for the :plan plan', { plan: waiting.requested })}</span>
                <Button type="button" size="sm" variant="ghost" onClick={() => router.delete(cancel.url(waiting.id), { preserveScroll: true })}>
                    {__('Withdraw')}
                </Button>
            </span>
        );
    }

    function send(event: FormEvent): void {
        event.preventDefault();

        form.post(upgrade.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset('note');
            },
        });
    }

    return (
        <>
            <Button type="button" size="sm" variant="outline" className={className} onClick={() => setOpen(true)} data-test="upgrade-request">
                <ArrowUpRight />
                {__('Ask for a bigger plan')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={send} className="space-y-5">
                        <DialogHeader>
                            <DialogTitle>{__('Ask for a bigger plan')}</DialogTitle>
                            <DialogDescription>
                                {plan.name ? __('You are on the :plan plan. The platform administrator gets your request and will get back to you.', { plan: plan.name }) : __('The platform administrator gets your request and will get back to you.')}
                            </DialogDescription>
                        </DialogHeader>

                        <div role="radiogroup" aria-label={__('Plan')} className="grid gap-2">
                            {plan.upgrades.map((option) => {
                                const selected = form.data.plan_id === option.id;

                                return (
                                    <label
                                        key={option.id}
                                        className={cn(
                                            'flex cursor-pointer items-start gap-3 rounded-lg border p-3 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
                                            selected ? 'border-primary ring-1 ring-primary' : 'hover:border-foreground/30',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="upgrade-plan"
                                            className="mt-1 accent-primary"
                                            checked={selected}
                                            onChange={() => form.setData('plan_id', option.id)}
                                        />
                                        <span className="grid gap-0.5 text-sm">
                                            <span className="font-semibold">{option.name}</span>
                                            <span className="text-muted-foreground">
                                                {__('Services: :services · Operators: :operators · Bookings per month: :bookings', {
                                                    services: option.services,
                                                    operators: option.operators,
                                                    bookings: option.bookings,
                                                })}
                                            </span>
                                            <span className="text-muted-foreground">{option.includes.join(' · ')}</span>
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        {form.errors.plan_id && <p className="text-sm text-destructive">{form.errors.plan_id}</p>}

                        <FormField id="upgrade-note" label={__('Note (optional)')} error={form.errors.note}>
                            <Textarea id="upgrade-note" rows={3} maxLength={500} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} />
                        </FormField>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                {__('Cancel')}
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {__('Send request')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
