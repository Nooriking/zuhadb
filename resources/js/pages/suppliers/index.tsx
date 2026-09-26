// resources/js/pages/suppliers/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { DeleteAction, EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Banknote, Mail, Phone, Plus, ToggleLeft, ToggleRight, Trash2, Truck, User as UserIcon, X } from 'lucide-react';
import { useRef, useState } from 'react';

interface Supplier {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    contact_person: string | null;
    status: 'active' | 'inactive';
    purchases_count: number;
    paid_afn: number;
    balance_afn: number;
    creator: { id: number; name: string } | null;
}

interface OpenBill {
    id: number;
    invoice_no: string;
    due_afn: number;
    purchase_date: string | null;
}

interface Debtor {
    id: number;
    name: string;
    phone: string | null;
    balance_afn: number;
    bills: OpenBill[];
}

interface Props {
    suppliers: {
        data: Supplier[];
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
    };
    debtors: Debtor[];
    exchangeRate: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Suppliers', href: '/suppliers' },
];

export default function SuppliersIndex({ suppliers, filters, debtors, exchangeRate }: Props) {
    const { toast } = useToast();
    const paySection = useRef<HTMLDivElement>(null);
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [supplierToDelete, setSupplierToDelete] = useState<Supplier | null>(null);
    const [paySupplierId, setPaySupplierId] = useState(debtors[0] ? String(debtors[0].id) : '');
    const payForm = useForm({
        amount_afn: debtors[0] ? String(debtors[0].balance_afn) : '',
        invoice_id: '',
        payment_date: new Date().toISOString().slice(0, 10),
        exchange_rate: exchangeRate ? String(exchangeRate) : '',
        payment_method: 'cash',
        notes: '',
    });
    const selectedDebtor = debtors.find((row) => String(row.id) === paySupplierId) ?? null;

    const chooseDebtor = (id: string, billId = '') => {
        const debtor = debtors.find((row) => String(row.id) === id);
        setPaySupplierId(id);
        const bill = debtor?.bills.find((row) => String(row.id) === billId);
        payForm.setData({
            ...payForm.data,
            invoice_id: billId,
            amount_afn: bill ? String(bill.due_afn) : debtor ? String(debtor.balance_afn) : '',
        });
        paySection.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const submitPayment = (event: React.FormEvent) => {
        event.preventDefault();
        if (!selectedDebtor) return;
        payForm.post(`/suppliers/${selectedDebtor.id}/payments`);
    };

    const handleSearch = () => {
        router.get(
            '/suppliers',
            {
                search: search || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleToggleStatus = (supplier: Supplier) => {
        router.patch(
            `/suppliers/${supplier.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Status Changed', description: `${supplier.name} status updated.` }),
                onError: () => toast({ title: 'Error', description: 'An error occurred.', variant: 'destructive' }),
            },
        );
    };

    const confirmDelete = () => {
        if (!supplierToDelete) return;

        router.delete(`/suppliers/${supplierToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to Trash', description: `${supplierToDelete.name} moved to trash.` });
                setSupplierToDelete(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Cannot delete.', variant: 'destructive' });
                setSupplierToDelete(null);
            },
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const formatPrice = (value: string | number) => {
        const num = typeof value === 'string' ? parseFloat(value) : value;
        return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(num);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Suppliers" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Truck className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Suppliers</h1>
                        <Badge variant="outline">{suppliers.total} Suppliers</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/suppliers/transactions">
                            <Button variant="outline">Transactions</Button>
                        </Link>
                        <Link href="/suppliers/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/suppliers/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Supplier
                            </Button>
                        </Link>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by name, phone, email...">
                    <FilterField label="Status">
                        <Select
                            value={statusFilter}
                            onValueChange={(v) => {
                                setStatusFilter(v);
                                setTimeout(handleSearch, 0);
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
                </IndexFilters>

                <Card ref={paySection} id="pay-debts" className="border-amber-300">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Banknote className="h-5 w-5" /> Pay debts
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {debtors.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No supplier has a balance due.</p>
                        ) : (
                            <form onSubmit={submitPayment} className="space-y-4">
                                <p className="text-muted-foreground text-sm">
                                    The payment is taken off the open bills. The receipt then shows the amount paid and the balance still left.
                                </p>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="space-y-2">
                                        <Label>Supplier</Label>
                                        <Select value={paySupplierId} onValueChange={(value) => chooseDebtor(value)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose a supplier" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {debtors.map((row) => (
                                                    <SelectItem key={row.id} value={String(row.id)}>
                                                        {row.name} · {formatPrice(row.balance_afn)} AFN
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Which bill</Label>
                                        <Select value={payForm.data.invoice_id || 'all'} onValueChange={(value) => chooseDebtor(paySupplierId, value === 'all' ? '' : value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Whole account</SelectItem>
                                                {(selectedDebtor?.bills ?? []).map((bill) => (
                                                    <SelectItem key={bill.id} value={String(bill.id)}>
                                                        {bill.invoice_no} · {formatPrice(bill.due_afn)} AFN
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {payForm.errors.invoice_id && <p className="text-destructive text-xs">{payForm.errors.invoice_id}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Amount (AFN)</Label>
                                        <Input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={payForm.data.amount_afn}
                                            onChange={(event) => payForm.setData('amount_afn', event.target.value)}
                                            required
                                        />
                                        {payForm.errors.amount_afn && <p className="text-destructive text-xs">{payForm.errors.amount_afn}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Balance still due</Label>
                                        <p className="font-mono text-lg font-bold text-amber-800">{formatPrice(selectedDebtor?.balance_afn || 0)} AFN</p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Date</Label>
                                        <Input type="date" value={payForm.data.payment_date} onChange={(event) => payForm.setData('payment_date', event.target.value)} required />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>USD to AFN that day</Label>
                                        <Input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={payForm.data.exchange_rate}
                                            onChange={(event) => payForm.setData('exchange_rate', event.target.value)}
                                            required
                                        />
                                        {payForm.errors.exchange_rate && <p className="text-destructive text-xs">{payForm.errors.exchange_rate}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Method</Label>
                                        <Select value={payForm.data.payment_method} onValueChange={(value) => payForm.setData('payment_method', value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="cash">Cash</SelectItem>
                                                <SelectItem value="bank">Bank</SelectItem>
                                                <SelectItem value="mobile">Mobile</SelectItem>
                                                <SelectItem value="other">Other</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Notes</Label>
                                        <Textarea value={payForm.data.notes} onChange={(event) => payForm.setData('notes', event.target.value)} />
                                    </div>
                                </div>
                                <Button type="submit" disabled={payForm.processing || !selectedDebtor} className="gap-2">
                                    <Banknote className="h-4 w-4" /> Save and print receipt
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead className="text-center">Purchases</TableHead>
                                <TableHead className="text-right">Paid</TableHead>
                                <TableHead className="text-right">Balance due</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {suppliers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-muted-foreground h-24 text-center">
                                        No suppliers found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                suppliers.data.map((supplier, index) => (
                                    <TableRow key={supplier.id}>
                                        <TableCell>{suppliers.from ? suppliers.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div>
                                                <Link href={`/suppliers/${supplier.id}`} className="font-medium hover:underline">
                                                    {supplier.name}
                                                </Link>
                                                {supplier.contact_person && (
                                                    <div className="text-muted-foreground flex items-center gap-1 text-xs">
                                                        <UserIcon className="h-3 w-3" />
                                                        {supplier.contact_person}
                                                    </div>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="space-y-1 text-sm">
                                                {supplier.phone && (
                                                    <div className="flex items-center gap-1">
                                                        <Phone className="text-muted-foreground h-3 w-3" />
                                                        <span dir="ltr">{supplier.phone}</span>
                                                    </div>
                                                )}
                                                {supplier.email && (
                                                    <div className="flex items-center gap-1">
                                                        <Mail className="text-muted-foreground h-3 w-3" />
                                                        <span className="truncate">{supplier.email}</span>
                                                    </div>
                                                )}
                                                {!supplier.phone && !supplier.email && <span className="text-muted-foreground">—</span>}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge variant="secondary">{supplier.purchases_count}</Badge>
                                        </TableCell>
                                        <TableCell className="text-right font-mono">{formatPrice(supplier.paid_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono font-semibold">
                                            {(supplier.balance_afn || 0) > 0 ? (
                                                <span className="text-amber-800">{formatPrice(supplier.balance_afn)} AFN</span>
                                            ) : (
                                                <span className="text-emerald-700">0</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {supplier.status === 'active' ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : (
                                                <Badge variant="secondary">Inactive</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <RowActions>
                                                {(supplier.balance_afn || 0) > 0 && (
                                                    <RowAction title="Pay debt" onClick={() => chooseDebtor(String(supplier.id))}>
                                                        <Banknote className="h-4 w-4" />
                                                    </RowAction>
                                                )}
                                                <ViewAction href={`/suppliers/${supplier.id}`} />
                                                <EditAction href={`/suppliers/${supplier.id}/edit`} />
                                                <RowAction title="Toggle Status" onClick={() => handleToggleStatus(supplier)}>
                                                    {supplier.status === 'active' ? (
                                                        <ToggleRight className="h-4 w-4 text-green-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-4 w-4 text-gray-400" />
                                                    )}
                                                </RowAction>
                                                <DeleteAction onClick={() => setSupplierToDelete(supplier)} />
                                            </RowActions>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {suppliers.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {suppliers.from || 0} to {suppliers.to || 0} of {suppliers.total} results
                        </div>
                        <div className="flex gap-1">
                            {suppliers.links.map((link, i) => (
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
            {supplierToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setSupplierToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Are you sure?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Supplier <strong>{supplierToDelete.name}</strong> will be moved to trash.
                                </p>
                            </div>
                            <button type="button" onClick={() => setSupplierToDelete(null)} className="text-muted-foreground hover:text-foreground">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setSupplierToDelete(null)}>
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
