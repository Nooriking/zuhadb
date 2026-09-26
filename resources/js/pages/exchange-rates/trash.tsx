// resources/js/pages/exchange-rates/trash.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RefreshCw, RotateCcw, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
}

interface ExchangeRate {
    id: number;
    rate: string;
    effective_at: string;
    from_currency: Currency | null;
    to_currency: Currency | null;
}

interface Props {
    exchangeRates: {
        data: ExchangeRate[];
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
    { title: 'Exchange Rates', href: '/exchange-rates' },
    { title: 'Trash', href: '/exchange-rates/trash' },
];

export default function ExchangeRatesTrash({ exchangeRates }: Props) {
    const { toast } = useToast();
    const [rateToDelete, setRateToDelete] = useState<ExchangeRate | null>(null);

    const handleRestore = (rate: ExchangeRate) => {
        router.post(
            `/exchange-rates/${rate.id}/restore`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Restored', description: 'Exchange rate was restored.' }),
                onError: () => toast({ title: 'Error', description: 'Failed to restore.', variant: 'destructive' }),
            },
        );
    };

    const confirmForceDelete = () => {
        if (!rateToDelete) return;

        router.delete(`/exchange-rates/${rateToDelete.id}/force-delete`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Deleted', description: 'Exchange rate was permanently deleted.' });
                setRateToDelete(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Failed to delete.', variant: 'destructive' });
                setRateToDelete(null);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Exchange Rates" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2 className="text-destructive h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Exchange Rates</h1>
                        <Badge variant="outline">{exchangeRates.total} Rates</Badge>
                    </div>
                    <Link href="/exchange-rates">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Exchange Rates
                        </Button>
                    </Link>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead className="text-center">Rate</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Effective Date</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {exchangeRates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        <div className="flex flex-col items-center gap-2">
                                            <RefreshCw className="text-muted-foreground h-8 w-8" />
                                            <p>Trash is empty.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                exchangeRates.data.map((rate, index) => (
                                    <TableRow key={rate.id}>
                                        <TableCell>{exchangeRates.from ? exchangeRates.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="font-mono">
                                                {rate.from_currency?.code || '—'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-center font-mono font-bold">{rate.rate}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="font-mono">
                                                {rate.to_currency?.code || '—'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm">{new Date(rate.effective_at).toLocaleDateString('en-US')}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Button variant="ghost" size="icon" title="Restore" onClick={() => handleRestore(rate)}>
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setRateToDelete(rate)}
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

            {rateToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setRateToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <Trash2 className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Delete forever?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    This exchange rate will be <strong>permanently deleted</strong>. This action cannot be undone.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setRateToDelete(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setRateToDelete(null)}>
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
