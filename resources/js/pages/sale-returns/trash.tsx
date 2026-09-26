import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface SaleReturn {
    id: number;
    return_number: string;
    return_date: string;
    total_afn: string;
    sale: { id: number; invoice_number: string } | null;
    customer: { id: number; name: string } | null;
}

interface Props {
    saleReturns: {
        data: SaleReturn[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sale Returns', href: '/sale-returns' },
    { title: 'Trash', href: '/sale-returns/trash' },
];

export default function SaleReturnsTrash({ saleReturns }: Props) {
    const [pendingId, setPendingId] = useState<number | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sale return trash" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Sale return trash</h1>
                    <Link href="/sale-returns">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Return</TableHead>
                                <TableHead>Invoice</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {saleReturns.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        Trash is empty.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                saleReturns.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-mono text-xs">{row.return_number}</TableCell>
                                        <TableCell className="font-mono text-xs">{row.sale?.invoice_number || '—'}</TableCell>
                                        <TableCell>{row.customer?.name || 'Walk-in'}</TableCell>
                                        <TableCell className="text-xs">{row.return_date}</TableCell>
                                        <TableCell className="text-right">{row.total_afn} AFN</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="gap-1"
                                                    onClick={() => router.post(`/sale-returns/${row.id}/restore`)}
                                                >
                                                    <RotateCcw className="h-3 w-3" /> Restore
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="gap-1 text-red-600"
                                                    onClick={() => setPendingId(row.id)}
                                                >
                                                    <Trash2 className="h-3 w-3" /> Delete
                                                </Button>
                                            </div>
                                            {pendingId === row.id && (
                                                <Button
                                                    size="sm"
                                                    className="mt-2 bg-red-600 text-white"
                                                    onClick={() => router.delete(`/sale-returns/${row.id}/force-delete`)}
                                                >
                                                    Confirm delete
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
