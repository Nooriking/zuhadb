// resources/js/pages/investors/trash.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import AppLayout from '@/layouts/app-layout';
import { forceDeleteItem, restoreItem } from '@/lib/trash-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Trash2, Users } from 'lucide-react';

interface Investor {
    id: number;
    full_name: string;
    phone: string | null;
    email: string | null;
    status: 'active' | 'inactive';
    wallet: {
        capital_balance_afn: string;
        profit_balance_afn: string;
    } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    investors: {
        data: Investor[];
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
    { title: 'Investors', href: '/investors' },
    { title: 'Trash', href: '/investors/trash' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

export default function InvestorsTrash({ investors }: Props) {

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Investors" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2 className="text-destructive h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Investors</h1>
                        <Badge variant="outline">{investors.total} Investors</Badge>
                    </div>
                    <Link href="/investors">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Investors
                        </Button>
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Investor</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead className="text-right">Capital</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {investors.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        <div className="flex flex-col items-center gap-2">
                                            <Users className="text-muted-foreground h-8 w-8" />
                                            <p>Trash is empty.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                investors.data.map((investor, index) => (
                                    <TableRow key={investor.id}>
                                        <TableCell>{investors.from ? investors.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="font-medium">{investor.full_name}</div>
                                            {investor.creator && <div className="text-muted-foreground text-xs">By: {investor.creator.name}</div>}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            <div dir="ltr">{investor.phone || '—'}</div>
                                            <div className="text-muted-foreground text-xs">{investor.email || ''}</div>
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs">
                                            {formatPrice(investor.wallet?.capital_balance_afn || 0)} USD
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{investor.status}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Button variant="ghost" size="icon" title="Restore" onClick={() => void restoreItem(investor.full_name, `/investors/${investor.id}/restore`)}>
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => void forceDeleteItem(investor.full_name, `/investors/${investor.id}/force-delete`)}
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

                {investors.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {investors.from || 0} to {investors.to || 0} of {investors.total} results
                        </div>
                        <div className="flex gap-1">
                            {investors.links.map((link, i) => (
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

        </AppLayout>
    );
}
