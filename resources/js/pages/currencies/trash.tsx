// resources/js/pages/currencies/trash.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Coins, RotateCcw, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    is_base: boolean;
    status: 'active' | 'inactive';
}

interface Props {
    currencies: {
        data: Currency[];
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
    { title: 'Currencies', href: '/currencies' },
    { title: 'Trash', href: '/currencies/trash' },
];

export default function CurrenciesTrash({ currencies }: Props) {
    const { toast } = useToast();
    const [currencyToDelete, setCurrencyToDelete] = useState<Currency | null>(null);

    const handleRestore = (currency: Currency) => {
        router.post(
            `/currencies/${currency.id}/restore`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Restored', description: `Currency ${currency.code} was restored.` }),
                onError: () => toast({ title: 'Error', description: 'Failed to restore.', variant: 'destructive' }),
            },
        );
    };

    const confirmForceDelete = () => {
        if (!currencyToDelete) return;

        router.delete(`/currencies/${currencyToDelete.id}/force-delete`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Deleted', description: `Currency ${currencyToDelete.code} was permanently deleted.` });
                setCurrencyToDelete(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Failed to delete.', variant: 'destructive' });
                setCurrencyToDelete(null);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Currencies" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2 className="text-destructive h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Currencies</h1>
                        <Badge variant="outline">{currencies.total} Currencies</Badge>
                    </div>
                    <Link href="/currencies">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Currencies
                        </Button>
                    </Link>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Symbol</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {currencies.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        <div className="flex flex-col items-center gap-2">
                                            <Coins className="text-muted-foreground h-8 w-8" />
                                            <p>Trash is empty.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                currencies.data.map((currency, index) => (
                                    <TableRow key={currency.id}>
                                        <TableCell>{currencies.from ? currencies.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <span className="font-mono font-semibold">{currency.code}</span>
                                        </TableCell>
                                        <TableCell>{currency.name}</TableCell>
                                        <TableCell className="text-lg">{currency.symbol || '—'}</TableCell>
                                        <TableCell>
                                            {currency.status === 'active' ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : (
                                                <Badge variant="secondary">Inactive</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Button variant="ghost" size="icon" title="Restore" onClick={() => handleRestore(currency)}>
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setCurrencyToDelete(currency)}
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

            {currencyToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setCurrencyToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <Trash2 className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Delete forever?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Currency <strong>{currencyToDelete.code}</strong> will be <strong>permanently deleted</strong>. This action cannot
                                    be undone.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCurrencyToDelete(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setCurrencyToDelete(null)}>
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
