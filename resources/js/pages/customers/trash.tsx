// resources/js/pages/customers/trash.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Trash2, UserCircle, X } from 'lucide-react';
import { useState } from 'react';

interface Customer {
    id: number;
    name: string;
    phone: string | null;
    status: 'active' | 'inactive';
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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
    { title: 'Trash', href: '/customers/trash' },
];

export default function CustomersTrash({ customers }: Props) {
    const { toast } = useToast();
    const [customerToDelete, setCustomerToDelete] = useState<Customer | null>(null);

    const handleRestore = (customer: Customer) => {
        router.post(
            `/customers/${customer.id}/restore`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Restored', description: `${customer.name} restored.` }),
                onError: () => toast({ title: 'Error', description: 'Failed to restore.', variant: 'destructive' }),
            },
        );
    };

    const confirmForceDelete = () => {
        if (!customerToDelete) return;

        router.delete(`/customers/${customerToDelete.id}/force-delete`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Deleted', description: `${customerToDelete.name} permanently deleted.` });
                setCustomerToDelete(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Failed to delete.', variant: 'destructive' });
                setCustomerToDelete(null);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Customers" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2 className="text-destructive h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Customers</h1>
                        <Badge variant="outline">{customers.total} Customers</Badge>
                    </div>
                    <Link href="/customers">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Customers
                        </Button>
                    </Link>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Phone</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground h-24 text-center">
                                        <div className="flex flex-col items-center gap-2">
                                            <UserCircle className="text-muted-foreground h-8 w-8" />
                                            <p>Trash is empty.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                customers.data.map((customer, index) => (
                                    <TableRow key={customer.id}>
                                        <TableCell>{customers.from ? customers.from + index : index + 1}</TableCell>
                                        <TableCell className="font-medium">{customer.name}</TableCell>
                                        <TableCell dir="ltr">{customer.phone || '—'}</TableCell>
                                        <TableCell>
                                            {customer.status === 'active' ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : (
                                                <Badge variant="secondary">Inactive</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Button variant="ghost" size="icon" title="Restore" onClick={() => handleRestore(customer)}>
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setCustomerToDelete(customer)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {customerToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setCustomerToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <Trash2 className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Delete forever?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Customer <strong>{customerToDelete.name}</strong> will be <strong>permanently deleted</strong>.
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
                            <Button onClick={confirmForceDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Delete Forever
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
