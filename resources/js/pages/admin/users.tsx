import { Head, router } from '@inertiajs/react';
import { Ban, CircleCheck, Ellipsis, KeyRound, Trash2, TriangleAlert, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { CredentialsDialog } from '@/components/credentials-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField, InputError } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { __, trans_choice } from '@/lib/i18n';
import { users as usersRoute } from '@/routes/admin';
import * as userRoutes from '@/routes/admin/users';
import type { Paginated } from '@/types/pagination';

interface Account {
    id: number;
    name: string;
    email: string;
    initials: string;
    avatar: string | null;
    role: string;
    role_color: string;
    portal: string | null;
    is_active: boolean;
    is_self: boolean;
    upcoming_count: number;
}

type Option = { id: string; name: string };

interface Props {
    accounts: Paginated<Account>;
    filters: { q: string; role: string; portal: string };
    roles: Option[];
    portals: Option[];
}

function FilterSelect({ id, label, value, onChange, all, options, className }: { id: string; label: string; value: string; onChange: (value: string) => void; all: string; options: Option[]; className?: string }) {
    return (
        <FormField id={id} label={label} className={className}>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{all}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.id} value={option.id}>
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FormField>
    );
}

function DeleteDialog({ account, onClose }: { account: Account | null; onClose: () => void }) {
    const [error, setError] = useState<string | undefined>();
    const blocked = (account?.upcoming_count ?? 0) > 0;

    function close(): void {
        setError(undefined);
        onClose();
    }

    return (
        <Dialog open={account !== null} onOpenChange={(open) => !open && close()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Delete this account?')}</DialogTitle>
                    <DialogDescription>{__('The account and its availability will be permanently removed.')}</DialogDescription>
                </DialogHeader>

                {account && blocked && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertDescription>
                            {trans_choice(
                                'This account still has one upcoming booking. Cancel or reassign it first.|This account still has :count upcoming bookings. Cancel or reassign them first.',
                                account.upcoming_count,
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <InputError message={error} />

                <DialogFooter>
                    <Button variant="outline" onClick={close}>
                        {__('Cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={blocked}
                        onClick={() =>
                            account && router.delete(userRoutes.destroy.url(account.id), { preserveScroll: true, onSuccess: close, onError: (errors) => setError(errors.account) })
                        }
                    >
                        {__('Delete account')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Users({ accounts, filters: current, roles, portals }: Props) {
    const [filters, setFilter] = useFilters(usersRoute.url(), current, { q: '', role: 'all', portal: 'all' });
    const [deleting, setDeleting] = useState<Account | null>(null);

    return (
        <>
            <Head title={__('Accounts')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Accounts')} subheading={__('Every account on the platform, across all portals.')} />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <SearchInput id="account-search" value={filters.q} onChange={(value) => setFilter('q', value)} placeholder={__('Search by name or email')} className="sm:max-w-sm sm:flex-1" />
                    <FilterSelect id="account-role" label={__('Role')} value={filters.role} onChange={(value) => setFilter('role', value)} all={__('All roles')} options={roles} className="sm:w-44" />
                    <FilterSelect id="account-portal" label={__('Portal')} value={filters.portal} onChange={(value) => setFilter('portal', value)} all={__('All portals')} options={portals} className="sm:w-56" />
                </div>

                {accounts.data.length === 0 ? (
                    <EmptyState icon={UsersRound} title={__('No account matches these filters.')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="ps-4">{__('Name')}</TableHead>
                                    <TableHead>{__('Role')}</TableHead>
                                    <TableHead>{__('Portal')}</TableHead>
                                    <TableHead>{__('Status')}</TableHead>
                                    <TableHead className="pe-4" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {accounts.data.map((account) => (
                                    <TableRow key={account.id}>
                                        <TableCell className="ps-4">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-8">
                                                    {account.avatar && <AvatarImage src={account.avatar} alt={account.name} />}
                                                    <AvatarFallback>{account.initials}</AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">{account.name}</div>
                                                    <div className="truncate text-sm text-muted-foreground">{account.email}</div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <ColorBadge color={account.role_color}>{account.role}</ColorBadge>
                                        </TableCell>
                                        <TableCell>{account.portal ?? '—'}</TableCell>
                                        <TableCell>
                                            <ColorBadge color={account.is_active ? 'emerald' : 'zinc'}>{account.is_active ? __('Active') : __('Disabled')}</ColorBadge>
                                        </TableCell>
                                        <TableCell className="pe-4 text-end">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button size="icon" variant="ghost" aria-label={__('Account actions')}>
                                                        <Ellipsis />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onSelect={() => router.post(userRoutes.password.url(account.id), {}, { preserveScroll: true })}>
                                                        <KeyRound />
                                                        {__('Issue new password')}
                                                    </DropdownMenuItem>
                                                    {!account.is_self && (
                                                        <>
                                                            <DropdownMenuItem onSelect={() => router.patch(userRoutes.toggle.url(account.id), {}, { preserveScroll: true })}>
                                                                {account.is_active ? <Ban /> : <CircleCheck />}
                                                                {account.is_active ? __('Disable') : __('Enable')}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem variant="destructive" onSelect={() => setDeleting(account)}>
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

                <Pagination paginator={accounts} />
            </div>

            <DeleteDialog account={deleting} onClose={() => setDeleting(null)} />
            <CredentialsDialog title={__('New password issued')} description={__('Share it with the account owner. It is shown only once.')} />
        </>
    );
}
