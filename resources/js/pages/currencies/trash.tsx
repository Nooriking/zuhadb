// resources/js/pages/currencies/trash.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import AppLayout from '@/layouts/app-layout';
import { forceDeleteItem, restoreItem } from '@/lib/trash-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Coins, RotateCcw, Trash2 } from 'lucide-react';

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
                                                <Button variant="ghost" size="icon" title="Restore" onClick={() => void restoreItem(currency.code, `/currencies/${currency.id}/restore`)}>
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => void forceDeleteItem(currency.code, `/currencies/${currency.id}/force-delete`)}
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

        </AppLayout>
    );
}
