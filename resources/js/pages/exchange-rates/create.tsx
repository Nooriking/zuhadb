import { BackButton } from '@/components/form-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { RefreshCw, Save } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface Props {
    currencies: Currency[];
    sources?: string[];
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exchange Rates', href: '/exchange-rates' },
    { title: 'Create Rate', href: '/exchange-rates/create' },
];

const DEFAULT_SOURCES = ['manual', 'market', 'bank', 'other'];

export default function ExchangeRatesCreate({ currencies, sources = DEFAULT_SOURCES, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        from_currency_id: '',
        to_currency_id: '',
        rate: '',
        effective_at: new Date().toISOString().slice(0, 16),
        source: 'manual',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (loading) return;
        setLoading(true);
        setErrors({});

        router.post('/exchange-rates', form, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Exchange rate created successfully.' });
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({ title: 'Error', description: 'There was a problem creating the rate.', variant: 'destructive' });
            },
            onFinish: () => {
                // Keep disabled until redirect on success; unlock only on error path above.
            },
        });
    };

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Exchange Rate" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/exchange-rates" label="Back" />
                    <div className="flex items-center gap-3">
                        <RefreshCw className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Exchange Rate</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Exchange Rate Information</CardTitle>
                            <CardDescription>Define the rate between two currencies. Historical rows are preserved when you post a new rate.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="from_currency_id">
                                        From Currency <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.from_currency_id} onValueChange={(v) => handleChange('from_currency_id', v)}>
                                        <SelectTrigger id="from_currency_id" className={errors.from_currency_id ? 'border-destructive' : ''}>
                                            <SelectValue placeholder="Select source currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.code} — {c.name} {c.symbol && `(${c.symbol})`}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.from_currency_id && <p className="text-destructive text-sm">{errors.from_currency_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="to_currency_id">
                                        To Currency <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.to_currency_id} onValueChange={(v) => handleChange('to_currency_id', v)}>
                                        <SelectTrigger id="to_currency_id" className={errors.to_currency_id ? 'border-destructive' : ''}>
                                            <SelectValue placeholder="Select target currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.code} — {c.name} {c.symbol && `(${c.symbol})`}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.to_currency_id && <p className="text-destructive text-sm">{errors.to_currency_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="rate">
                                        Rate <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="rate"
                                        type="number"
                                        step="0.000001"
                                        min="0"
                                        value={form.rate}
                                        onChange={(e) => handleChange('rate', e.target.value)}
                                        placeholder="e.g. 70.500000"
                                        className={`font-mono ${errors.rate ? 'border-destructive' : ''}`}
                                    />
                                    {errors.rate && <p className="text-destructive text-sm">{errors.rate}</p>}
                                    <p className="text-muted-foreground text-xs">1 unit of From = X units of To</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="effective_at">
                                        Effective Date & Time <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="effective_at"
                                        type="datetime-local"
                                        value={form.effective_at}
                                        onChange={(e) => handleChange('effective_at', e.target.value)}
                                        className={errors.effective_at ? 'border-destructive' : ''}
                                    />
                                    {errors.effective_at && <p className="text-destructive text-sm">{errors.effective_at}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="source">Source</Label>
                                    <Select value={form.source} onValueChange={(v) => handleChange('source', v)}>
                                        <SelectTrigger id="source" className={errors.source ? 'border-destructive' : ''}>
                                            <SelectValue placeholder="Select source" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {sources.map((source) => (
                                                <SelectItem key={source} value={source} className="capitalize">
                                                    {source}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.source && <p className="text-destructive text-sm">{errors.source}</p>}
                                </div>
                            </div>

                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Save Exchange Rate'}
                                </Button>
                                <BackButton href="/exchange-rates" label="Cancel" />
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
