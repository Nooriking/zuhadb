import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';

interface Invoice {
    id: number;
    supplier_id: number;
    invoice_no: string;
    purchase_date: string | null;
    exchange_rate: string;
    total_afn: string;
    paid_afn: string;
    due_afn: string;
    notes: string | null;
    bill_image: string | null;
    supplier: { id: number; name: string } | null;
}

interface Props {
    invoices: {
        data: Invoice[];
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    suppliers: { id: number; name: string }[];
    filters: { supplier_id: string | null };
    summary: { total_afn: number; paid_afn: number; due_afn: number; count: number };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suppliers', href: '/suppliers' },
    { title: 'Transactions', href: '/suppliers/transactions' },
];

const money = (value: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof value === 'string' ? parseFloat(value) || 0 : value || 0);

export default function SupplierTransactions({ invoices, suppliers, filters, summary }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Supplier Transactions" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Supplier transactions</h1>
                        <p className="text-muted-foreground text-sm">Bill image, amount paid, and the balance left on each bill.</p>
                    </div>
                    <Link href="/suppliers">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Suppliers
                        </Button>
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm">Bills</CardTitle></CardHeader>
                        <CardContent className="text-2xl font-bold">{summary.count}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm">Bill total</CardTitle></CardHeader>
                        <CardContent className="font-mono text-xl font-semibold">{money(summary.total_afn)} AFN</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm">Paid</CardTitle></CardHeader>
                        <CardContent className="font-mono text-xl font-semibold text-emerald-700 dark:text-emerald-300">{money(summary.paid_afn)} AFN</CardContent>
                    </Card>
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40">
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-amber-900 dark:text-amber-200">Balance due</CardTitle></CardHeader>
                        <CardContent className="font-mono text-xl font-bold text-amber-950 dark:text-amber-100">{money(summary.due_afn)} AFN</CardContent>
                    </Card>
                </div>

                <Select
                    value={filters.supplier_id || 'all'}
                    onValueChange={(value) =>
                        router.get('/suppliers/transactions', value === 'all' ? {} : { supplier_id: value }, { preserveState: true })
                    }
                >
                    <SelectTrigger className="w-[260px]">
                        <SelectValue placeholder="Supplier" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All suppliers</SelectItem>
                        {suppliers.map((supplier) => (
                            <SelectItem key={supplier.id} value={String(supplier.id)}>{supplier.name}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Invoice</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="text-right">USD rate</TableHead>
                                <TableHead className="text-right">Bill total</TableHead>
                                <TableHead className="text-right">Paid</TableHead>
                                <TableHead className="text-right">Due</TableHead>
                                <TableHead>Bill image</TableHead>
                                <TableHead>Details</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
                                        No bill recorded yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                invoices.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <Link href={`/suppliers/${row.supplier_id}`} className="font-medium hover:underline">
                                                {row.supplier?.name || '—'}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="font-mono">{row.invoice_no}</TableCell>
                                        <TableCell>{row.purchase_date?.slice(0, 10) || '—'}</TableCell>
                                        <TableCell className="text-right font-mono">{row.exchange_rate || '—'}</TableCell>
                                        <TableCell className="text-right font-mono">{money(row.total_afn)}</TableCell>
                                        <TableCell className="text-right font-mono text-emerald-700">{money(row.paid_afn)}</TableCell>
                                        <TableCell className="text-right font-mono font-semibold">{money(row.due_afn)}</TableCell>
                                        <TableCell>
                                            {row.bill_image ? (
                                                <a href={`/storage/${row.bill_image}`} target="_blank" rel="noreferrer">
                                                    <img src={`/storage/${row.bill_image}`} alt="Bill" className="h-12 w-12 rounded border object-cover" />
                                                </a>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">No image</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col gap-1">
                                                {row.notes && <span className="text-muted-foreground max-w-[180px] truncate text-xs">{row.notes}</span>}
                                                <Link href={`/suppliers/${row.supplier_id}/invoices/${row.id}/receipt`}>
                                                    <Button variant="ghost" size="sm" className="gap-1">
                                                        <Printer className="h-4 w-4" /> Receipt
                                                    </Button>
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {invoices.last_page > 1 && (
                    <div className="flex flex-wrap gap-1">
                        {invoices.links.map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
