// resources/js/pages/exchange-rates/edit.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RefreshCw, Save } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface ExchangeRate {
    id: number;
    from_currency_id: number;
    to_currency_id: number;
    rate: string;
    effective_at: string;
}

interface Props {
    exchangeRate: ExchangeRate;
    currencies: Currency[];
    errors?: Record<string, string>;
}

export default function ExchangeRatesEdit({ exchangeRate, currencies, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        from_currency_id: String(exchangeRate.from_currency_id),
        to_currency_id: String(exchangeRate.to_currency_id),
        rate: exchangeRate.rate,
        effective_at: new Date(exchangeRate.effective_at).toISOString().slice(0, 16),
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Exchange Rates', href: '/exchange-rates' },
        { title: 'Edit', href: `/exchange-rates/${exchangeRate.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.put(`/exchange-rates/${exchangeRate.id}`, form, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Exchange rate updated successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({ title: 'Error', description: 'There was a problem updating the rate.', variant: 'destructive' });
            },
        });
    };

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Exchange Rate" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <RefreshCw className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Exchange Rate</h1>
                    </div>
                    <Link href="/exchange-rates">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit Exchange Rate</CardTitle>
                            <CardDescription>Update the rate information.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>
                                        From Currency <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.from_currency_id} onValueChange={(v) => handleChange('from_currency_id', v)}>
                                        <SelectTrigger className={errors.from_currency_id ? 'border-destructive' : ''}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.code} — {c.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.from_currency_id && <p className="text-destructive text-sm">{errors.from_currency_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        To Currency <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.to_currency_id} onValueChange={(v) => handleChange('to_currency_id', v)}>
                                        <SelectTrigger className={errors.to_currency_id ? 'border-destructive' : ''}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.code} — {c.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.to_currency_id && <p className="text-destructive text-sm">{errors.to_currency_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        Rate <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.000001"
                                        min="0"
                                        value={form.rate}
                                        onChange={(e) => handleChange('rate', e.target.value)}
                                        className={`font-mono ${errors.rate ? 'border-destructive' : ''}`}
                                    />
                                    {errors.rate && <p className="text-destructive text-sm">{errors.rate}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        Effective Date <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="datetime-local"
                                        value={form.effective_at}
                                        onChange={(e) => handleChange('effective_at', e.target.value)}
                                        className={errors.effective_at ? 'border-destructive' : ''}
                                    />
                                    {errors.effective_at && <p className="text-destructive text-sm">{errors.effective_at}</p>}
                                </div>
                            </div>

                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Update Exchange Rate'}
                                </Button>
                                <Link href="/exchange-rates">
                                    <Button variant="outline" type="button">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
