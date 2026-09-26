// resources/js/pages/customers/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { DeleteAction, EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    MapPin,
    Phone,
    Plus,
    ToggleLeft,
    ToggleRight,
    Trash2,
    UserCircle,
    Users,
    Wallet,
    X,
} from 'lucide-react';
import { useState } from 'react';

interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
    current_due_afn: number | string;
    credit_limit_afn: number | string;
    last_sale_at: string | null;
    sales_count: number;
    creator: { id: number; name: string } | null;
    created_at: string;
}

interface Stats {
    total_customers: number;
    active_customers: number;
    total_due_afn: number | string;
    debtors_count: number;
}

interface Props {
    customers: {
        data: Customer[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string | null;
        status: string | null;
        only_due: boolean;
    };
    stats: Stats;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
];

const fmtAfn = (v: number | string | null | undefined) => {
    const n = Number(v ?? 0);
    return `${n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} AFN`;
};

export default function CustomersIndex({ customers, filters, stats }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [onlyDue, setOnlyDue] = useState<boolean>(filters.only_due || false);
    const [customerToDelete, setCustomerToDelete] = useState<Customer | null>(null);

    const handleSearch = (overrides?: { status?: string; only_due?: boolean }) => {
        router.get(
            '/customers',
            {
                search: search || undefined,
                status: (overrides?.status ?? statusFilter) === 'all' ? undefined : (overrides?.status ?? statusFilter),
                only_due: (overrides?.only_due ?? onlyDue) ? 1 : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleToggleStatus = (customer: Customer) => {
        router.patch(
            `/customers/${customer.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Status Changed', description: `${customer.name} status updated.` }),
                onError: () => toast({ title: 'Error', description: 'An error occurred.', variant: 'destructive' }),
            },
        );
    };

    const confirmDelete = () => {
        if (!customerToDelete) return;

        router.delete(`/customers/${customerToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to Trash', description: `${customerToDelete.name} moved to trash.` });
                setCustomerToDelete(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Cannot delete.', variant: 'destructive' });
                setCustomerToDelete(null);
            },
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const dueColor = (_due: number) => 'text-foreground';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customers" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <UserCircle className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Customers</h1>
                        <Badge variant="outline">{customers.total} Customers</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/customers/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/customers/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Customer
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-muted-foreground text-sm">Total Customers</p>
                                <p className="mt-1 text-2xl font-bold">{stats.total_customers}</p>
                            </div>
                            <Users className="text-muted-foreground h-8 w-8" />
                        </div>
                    </div>
                    <div className="rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-muted-foreground text-sm">Active</p>
                                <p className="mt-1 text-2xl font-bold">{stats.active_customers}</p>
                            </div>
                            <UserCircle className="text-muted-foreground h-8 w-8" />
                        </div>
                    </div>
                    <div className="rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-muted-foreground text-sm">Total Due</p>
                                <p className="mt-1 text-2xl font-bold">{fmtAfn(stats.total_due_afn)}</p>
                            </div>
                            <Banknote className="text-muted-foreground h-8 w-8" />
                        </div>
                    </div>
                    <div className="rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-muted-foreground text-sm">Debtors</p>
                                <p className="mt-1 text-2xl font-bold">{stats.debtors_count}</p>
                            </div>
                            <Wallet className="text-muted-foreground h-8 w-8" />
                        </div>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={() => handleSearch()} placeholder="Search by name, phone, address...">
                    <FilterField label="Status">
                        <Select
                            value={statusFilter}
                            onValueChange={(v) => {
                                setStatusFilter(v);
                                setTimeout(() => handleSearch({ status: v }), 0);
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                    <FilterField label="Due">
                        <Button
                            type="button"
                            variant={onlyDue ? 'default' : 'outline'}
                            onClick={() => {
                                const next = !onlyDue;
                                setOnlyDue(next);
                                setTimeout(() => handleSearch({ only_due: next }), 0);
                            }}
                            className="w-full gap-2"
                        >
                            <Banknote className="h-4 w-4" />
                            Only with Due
                        </Button>
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Address</TableHead>
                                <TableHead className="text-center">Purchases</TableHead>
                                <TableHead className="text-right">Current Due</TableHead>
                                <TableHead className="text-right">Credit Limit</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
                                        No customers found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                customers.data.map((customer, index) => {
                                    const due = Number(customer.current_due_afn ?? 0);
                                    const limit = Number(customer.credit_limit_afn ?? 0);
                                    const exceeded = limit > 0 && due > limit;

                                    return (
                                        <TableRow key={customer.id}>
                                            <TableCell>{customers.from ? customers.from + index : index + 1}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-full border">
                                                        <UserCircle className="text-muted-foreground h-6 w-6" />
                                                    </div>
                                                    <div className="font-medium">{customer.name}</div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {customer.phone ? (
                                                    <div className="flex items-center gap-1 text-sm">
                                                        <Phone className="text-muted-foreground h-3 w-3" />
                                                        <span dir="ltr">{customer.phone}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {customer.address ? (
                                                    <div className="flex items-start gap-1 text-sm">
                                                        <MapPin className="text-muted-foreground mt-0.5 h-3 w-3 shrink-0" />
                                                        <span className="max-w-xs truncate">{customer.address}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge variant="secondary">{customer.sales_count}</Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <span className={`font-medium ${dueColor(due)}`}>{fmtAfn(due)}</span>
                                                {exceeded && (
                                                    <div className="text-destructive mt-0.5 flex items-center justify-end gap-1 text-xs">
                                                        <AlertTriangle className="h-3 w-3" />
                                                        Exceeded
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-right">{limit > 0 ? fmtAfn(limit) : '—'}</TableCell>
                                            <TableCell>
                                                {customer.status === 'active' ? (
                                                    <Badge variant="success">Active</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Inactive</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <RowActions>
                                                    <ViewAction href={`/customers/${customer.id}`} />
                                                    {due > 0 && (
                                                        <RowAction title="Pay due" href={`/customers/${customer.id}#pay-due`}>
                                                            <Banknote className="h-4 w-4" />
                                                        </RowAction>
                                                    )}
                                                    <EditAction href={`/customers/${customer.id}/edit`} />
                                                    <RowAction title="Toggle Status" onClick={() => handleToggleStatus(customer)}>
                                                        {customer.status === 'active' ? (
                                                            <ToggleRight className="h-4 w-4" />
                                                        ) : (
                                                            <ToggleLeft className="h-4 w-4 text-gray-400" />
                                                        )}
                                                    </RowAction>
                                                    <DeleteAction onClick={() => setCustomerToDelete(customer)} />
                                                </RowActions>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {customers.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {customers.from || 0} to {customers.to || 0} of {customers.total} results
                        </div>
                        <div className="flex gap-1">
                            {customers.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => handlePageChange(link.url)}
                                    disabled={!link.url}
                                    className="min-w-[40px]"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Delete Modal */}
            {customerToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setCustomerToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Are you sure?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Customer <strong>{customerToDelete.name}</strong> will be moved to trash.
                                </p>
                            </div>
                            <button type="button" onClick={() => setCustomerToDelete(null)} className="text-muted-foreground hover:text-foreground">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setCustomerToDelete(null)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Move to Trash
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
