import { Head, router } from '@inertiajs/react';
import { CircleCheck, RotateCw, Trash2, TriangleAlert } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __, trans_choice } from '@/lib/i18n';
import * as queueRoutes from '@/routes/admin/queue';

interface Props {
    failures: { uuid: string; failed_at: string; job: string; reason: string }[];
    failuresToday: number;
}

export default function Queue({ failures, failuresToday }: Props) {
    return (
        <>
            <Head title={__('Queue')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader
                    heading={__('Queue')}
                    subheading={__('Background jobs that failed. A failed email never reached anybody.')}
                    actions={
                        failures.length > 0 && (
                            <Button size="sm" onClick={() => router.post(queueRoutes.retryAll.url(), {}, { preserveScroll: true })}>
                                <RotateCw />
                                {__('Retry all')}
                            </Button>
                        )
                    }
                />

                {failuresToday > 0 && (
                    <Alert>
                        <TriangleAlert />
                        <AlertDescription>
                            {trans_choice(
                                'One job failed in the last 24 hours. The administrators get an hourly digest.|:count jobs failed in the last 24 hours. The administrators get an hourly digest.',
                                failuresToday,
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {failures.length === 0 ? (
                    <EmptyState icon={CircleCheck} title={__('Nothing failed')}>
                        {__('Every background job went through.')}
                    </EmptyState>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-4">{__('When')}</TableHead>
                                    <TableHead>{__('Job')}</TableHead>
                                    <TableHead>{__('Why')}</TableHead>
                                    <TableHead className="pe-4" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {failures.map((failure) => (
                                    <TableRow key={failure.uuid}>
                                        <TableCell className="ps-4 tabular-nums">{failure.failed_at}</TableCell>
                                        <TableCell className="font-medium">{failure.job}</TableCell>
                                        <TableCell className="text-sm whitespace-normal text-muted-foreground">{failure.reason}</TableCell>
                                        <TableCell className="pe-4">
                                            <div className="flex justify-end gap-1">
                                                <Button size="sm" variant="ghost" onClick={() => router.post(queueRoutes.retry.url(failure.uuid), {}, { preserveScroll: true })}>
                                                    <RotateCw />
                                                    {__('Retry')}
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={__('Remove')}
                                                    title={__('Remove')}
                                                    onClick={() => router.delete(queueRoutes.destroy.url(failure.uuid), { preserveScroll: true })}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}
