import { BackButton } from '@/components/form-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatRate } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Edit, RefreshCw } from 'lucide-react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface ExchangeRate {
    id: number;
    rate: string;
    effective_at: string;
    source: string | null;
    from_currency: Currency | null;
    to_currency: Currency | null;
    creator: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    exchangeRate: ExchangeRate;
}

const formatDateTime = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export default function ExchangeRatesShow({ exchangeRate }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Exchange Rates', href: '/exchange-rates' },
        { title: 'View', href: `/exchange-rates/${exchangeRate.id}` },
    ];

    const rateValue = parseFloat(exchangeRate.rate);
    const inverse = Number.isFinite(rateValue) && rateValue > 0 ? formatRate(1 / rateValue) : '—';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="View Exchange Rate" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="bg-primary text-primary-foreground flex h-14 w-14 items-center justify-center rounded-lg">
                            <RefreshCw className="h-7 w-7" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Exchange Rate</h1>
                            <p className="text-muted-foreground mt-1 font-mono text-sm">
                                {exchangeRate.from_currency?.code} → {exchangeRate.to_currency?.code}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={`/exchange-rates/${exchangeRate.id}/edit`}>
                            <Button variant="outline" className="gap-2">
                                <Edit className="h-4 w-4" /> Edit
                            </Button>
                        </Link>
                        <BackButton href="/exchange-rates" label="Back" />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Rate Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="bg-muted/40 flex items-center justify-center gap-6 rounded-lg border p-6">
                                <div className="text-center">
                                    <p className="font-mono text-3xl font-bold">1</p>
                                    <p className="text-muted-foreground mt-1 text-sm">{exchangeRate.from_currency?.code}</p>
                                    <p className="text-muted-foreground text-xs">{exchangeRate.from_currency?.name}</p>
                                </div>
                                <ArrowRight className="text-muted-foreground h-8 w-8" />
                                <div className="text-center">
                                    <p className="text-primary font-mono text-3xl font-bold">{formatRate(exchangeRate.rate)}</p>
                                    <p className="text-muted-foreground mt-1 text-sm">{exchangeRate.to_currency?.code}</p>
                                    <p className="text-muted-foreground text-xs">{exchangeRate.to_currency?.name}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-sm">Effective Date & Time</p>
                                    <p className="font-medium">{formatDateTime(exchangeRate.effective_at)}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Source</p>
                                    <Badge variant="secondary" className="mt-1 capitalize">
                                        {exchangeRate.source || 'manual'}
                                    </Badge>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Created By</p>
                                    <p className="font-medium">{exchangeRate.creator?.name || 'System'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Created At</p>
                                    <p className="text-sm">{formatDateTime(exchangeRate.created_at)}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Updated At</p>
                                    <p className="text-sm">{formatDateTime(exchangeRate.updated_at)}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Info</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-muted-foreground text-sm">From</span>
                                <span className="font-mono font-medium">{exchangeRate.from_currency?.code}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-muted-foreground text-sm">To</span>
                                <span className="font-mono font-medium">{exchangeRate.to_currency?.code}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-muted-foreground text-sm">Rate</span>
                                <span className="font-mono font-bold">{formatRate(exchangeRate.rate)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Inverse</span>
                                <span className="font-mono font-medium">{inverse}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
