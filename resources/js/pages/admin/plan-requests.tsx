import { Head, useForm } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update } from '@/routes/admin/plan-requests';

interface PlanRequest {
    id: number;
    portal: string;
    portal_url: string;
    requester: string | null;
    requester_email: string | null;
    from: string | null;
    to: string;
    reason: string | null;
    note: string | null;
    status: string;
    label: string;
    color: string;
    open: boolean;
    response: string | null;
    date: string;
}

type Answer = 'in_progress' | 'handled' | 'rejected';

function AnswerDialog({ request, onClose }: { request: PlanRequest; onClose: () => void }) {
    const form = useForm<{ status: Answer; response: string }>({ status: request.status === 'in_progress' ? 'handled' : 'in_progress', response: '' });

    const choices: { value: Answer; label: string; hint: string }[] = [
        { value: 'in_progress', label: __('Taken in charge'), hint: __('The manager is told you are working on it.') },
        { value: 'handled', label: __('Handled'), hint: __('The plan was changed. It closes by itself when the plan of the portal reaches the one asked for.') },
        { value: 'rejected', label: __('Rejected'), hint: __('Say why: the manager reads it.') },
    ];

    function send(event: FormEvent): void {
        event.preventDefault();

        form.patch(update.url(request.id), { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={send} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>{__('Answer the request')}</DialogTitle>
                        <DialogDescription>
                            {request.portal}: {request.from ?? __('no plan')} → {request.to}
                        </DialogDescription>
                    </DialogHeader>

                    <div role="radiogroup" aria-label={__('State')} className="grid gap-2">
                        {choices.map((choice) => (
                            <label
                                key={choice.value}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-lg border p-3 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
                                    form.data.status === choice.value ? 'border-primary ring-1 ring-primary' : 'hover:border-foreground/30',
                                )}
                            >
                                <input type="radio" name="answer" className="mt-1 accent-primary" checked={form.data.status === choice.value} onChange={() => form.setData('status', choice.value)} />
                                <span className="grid gap-0.5 text-sm">
                                    <span className="font-semibold">{choice.label}</span>
                                    <span className="text-muted-foreground">{choice.hint}</span>
                                </span>
                            </label>
                        ))}
                    </div>

                    <FormField id="answer-response" label={form.data.status === 'rejected' ? __('Reason') : __('Message for the manager (optional)')} error={form.errors.response}>
                        <Textarea id="answer-response" rows={3} maxLength={500} value={form.data.response} onChange={(event) => form.setData('response', event.target.value)} />
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

export default function PlanRequests({ requests }: { requests: PlanRequest[] }) {
    const [answering, setAnswering] = useState<PlanRequest | null>(null);

    return (
        <>
            <Head title={__('Plan requests')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Plan requests')} subheading={__('What the managers ask for, and where each request stands.')} />

                {requests.length === 0 ? (
                    <EmptyState icon={ArrowUpRight} title={__('No requests yet')}>
                        <p>{__('When a manager asks for a bigger plan, the request shows up here.')}</p>
                    </EmptyState>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-4">{__('Portal')}</TableHead>
                                    <TableHead>{__('Request')}</TableHead>
                                    <TableHead>{__('State')}</TableHead>
                                    <TableHead>{__('Date')}</TableHead>
                                    <TableHead className="pe-4" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requests.map((request) => (
                                    <TableRow key={request.id}>
                                        <TableCell className="ps-4 whitespace-normal">
                                            <a href={request.portal_url} className="font-medium hover:underline">
                                                {request.portal}
                                            </a>
                                            {request.requester && (
                                                <div className="text-sm text-muted-foreground">
                                                    {request.requester} · {request.requester_email}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-sm whitespace-normal">
                                            <div className="font-medium">
                                                {request.from ?? __('no plan')} → {request.to}
                                            </div>
                                            {request.reason && <div className="text-sm text-muted-foreground">{request.reason}</div>}
                                            {request.note && <div className="mt-1 text-sm break-words">{request.note}</div>}
                                            {request.response && <div className="mt-1 text-sm break-words text-muted-foreground">{__('Answer')}: {request.response}</div>}
                                        </TableCell>
                                        <TableCell>
                                            <ColorBadge color={request.color}>{request.label}</ColorBadge>
                                        </TableCell>
                                        <TableCell className="text-sm whitespace-nowrap text-muted-foreground tabular-nums">{request.date}</TableCell>
                                        <TableCell className="pe-4 text-end">
                                            {request.open && (
                                                <Button size="sm" variant="outline" onClick={() => setAnswering(request)}>
                                                    {__('Answer')}
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            {answering && <AnswerDialog key={answering.id} request={answering} onClose={() => setAnswering(null)} />}
        </>
    );
}
