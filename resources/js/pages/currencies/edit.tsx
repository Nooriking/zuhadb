import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Coins, Save } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    decimal_places: number;
    rate_to_default: string | number;
    is_base: boolean;
    status: 'active' | 'inactive';
}

interface Props {
    currency: Currency;
    errors?: Record<string, string>;
    rateDefinition: string;
}

export default function CurrenciesEdit({ currency, errors: initialErrors, rateDefinition }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        code: currency.code,
        name: currency.name,
        symbol: currency.symbol || '',
        decimal_places: String(currency.decimal_places ?? 2),
        rate_to_default: String(currency.rate_to_default ?? '1'),
        is_base: currency.is_base,
        status: currency.status,
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Currencies', href: '/currencies' },
        { title: currency.code, href: `/currencies/${currency.id}` },
        { title: 'Edit', href: `/currencies/${currency.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.put(
            `/currencies/${currency.id}`,
            {
                ...form,
                decimal_places: Number(form.decimal_places),
            },
            {
                onSuccess: () => {
                    toast({ title: 'Success', description: `Currency "${form.code}" was updated successfully.` });
                    setLoading(false);
                },
                onError: (err) => {
                    setErrors(err);
                    setLoading(false);
                    toast({ title: 'Error', description: 'There was a problem updating the currency.', variant: 'destructive' });
                },
            },
        );
    };

    const handleChange = (field: string, value: string | boolean) => {
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
            <Head title={`Edit ${currency.code}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Coins className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Currency</h1>
                        <span className="text-muted-foreground font-mono">{currency.code}</span>
                    </div>
                    <Link href="/currencies">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                <form onSubmit={handleSubmit} aria-busy={loading}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit currency information</CardTitle>
                            <CardDescription>{rateDefinition}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="code">
                                        Currency code <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="code"
                                        value={form.code}
                                        onChange={(e) => handleChange('code', e.target.value.toUpperCase())}
                                        maxLength={3}
                                        required
                                        aria-invalid={Boolean(errors.code)}
                                        className={`font-mono uppercase ${errors.code ? 'border-destructive' : ''}`}
                                    />
                                    {errors.code && <p className="text-destructive text-sm">{errors.code}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="name">
                                        Currency name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        required
                                        aria-invalid={Boolean(errors.name)}
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="symbol">
                                        Symbol <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="symbol"
                                        value={form.symbol}
                                        onChange={(e) => handleChange('symbol', e.target.value)}
                                        maxLength={16}
                                        required
                                        aria-invalid={Boolean(errors.symbol)}
                                        className={`text-lg ${errors.symbol ? 'border-destructive' : ''}`}
                                    />
                                    {errors.symbol && <p className="text-destructive text-sm">{errors.symbol}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="decimal_places">
                                        Decimal places <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="decimal_places"
                                        type="number"
                                        min={0}
                                        max={8}
                                        value={form.decimal_places}
                                        onChange={(e) => handleChange('decimal_places', e.target.value)}
                                        required
                                        aria-invalid={Boolean(errors.decimal_places)}
                                        className={errors.decimal_places ? 'border-destructive' : ''}
                                    />
                                    {errors.decimal_places && <p className="text-destructive text-sm">{errors.decimal_places}</p>}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="rate_to_default">
                                        Exchange rate to default <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="rate_to_default"
                                        type="number"
                                        step="any"
                                        min="0.00000001"
                                        value={form.is_base ? '1' : form.rate_to_default}
                                        onChange={(e) => handleChange('rate_to_default', e.target.value)}
                                        disabled={form.is_base}
                                        required
                                        aria-invalid={Boolean(errors.rate_to_default)}
                                        className={errors.rate_to_default ? 'border-destructive' : ''}
                                    />
                                    {errors.rate_to_default && <p className="text-destructive text-sm">{errors.rate_to_default}</p>}
                                    <p className="text-muted-foreground text-xs">{rateDefinition}</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="status">
                                        Status <span className="text-destructive">*</span>
                                    </Label>
                                    <Select
                                        value={form.status}
                                        onValueChange={(v) => handleChange('status', v as 'active' | 'inactive')}
                                        disabled={form.is_base}
                                    >
                                        <SelectTrigger id="status" className={errors.status ? 'border-destructive' : ''}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.status && <p className="text-destructive text-sm">{errors.status}</p>}
                                </div>
                            </div>

                            <div className="rounded-lg border border-dashed p-4">
                                <div className="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        id="is_base"
                                        checked={form.is_base}
                                        disabled={currency.is_base}
                                        onChange={(e) => handleChange('is_base', e.target.checked)}
                                        className="h-4 w-4 rounded"
                                    />
                                    <div>
                                        <Label htmlFor="is_base" className="cursor-pointer">
                                            Default currency
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            Setting this as default will unset the previous default. Historical sales keep their original AFN amounts.
                                        </p>
                                    </div>
                                </div>
                                {errors.is_base && <p className="text-destructive mt-2 text-sm">{errors.is_base}</p>}
                            </div>

                            <div className="flex flex-wrap gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Update Currency'}
                                </Button>
                                <Link href={`/currencies/${currency.id}`}>
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
