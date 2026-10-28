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
import { Coins, Save } from 'lucide-react';
import { useState } from 'react';

interface Props {
    errors?: Record<string, string>;
    hasDefault: boolean;
    rateDefinition: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currencies', href: '/currencies' },
    { title: 'Create Currency', href: '/currencies/create' },
];

export default function CurrenciesCreate({ errors: initialErrors, hasDefault, rateDefinition }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        code: '',
        name: '',
        symbol: '',
        decimal_places: '2',
        rate_to_default: hasDefault ? '' : '1',
        is_base: !hasDefault,
        status: 'active' as 'active' | 'inactive',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post(
            '/currencies',
            {
                ...form,
                decimal_places: Number(form.decimal_places),
                rate_to_default: form.is_base && !hasDefault ? 1 : form.rate_to_default,
            },
            {
                onSuccess: () => {
                    toast({ title: 'Success', description: `Currency "${form.code}" was created successfully.` });
                    setLoading(false);
                },
                onError: (err) => {
                    setErrors(err);
                    setLoading(false);
                    toast({ title: 'Error', description: 'There was a problem creating the currency.', variant: 'destructive' });
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
            <Head title="Create Currency" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/currencies" label="Back" />
                    <div className="flex items-center gap-3">
                        <Coins className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Currency</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit} aria-busy={loading}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Currency information</CardTitle>
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
                                        placeholder="e.g. USD, AFN, EUR"
                                        maxLength={3}
                                        required
                                        aria-invalid={Boolean(errors.code)}
                                        className={`font-mono uppercase ${errors.code ? 'border-destructive' : ''}`}
                                    />
                                    {errors.code && <p className="text-destructive text-sm">{errors.code}</p>}
                                    <p className="text-muted-foreground text-xs">3-letter ISO code.</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="name">
                                        Currency name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        placeholder="e.g. US Dollar"
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
                                        placeholder="e.g. $ or ؋"
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
                                    <p className="text-muted-foreground text-xs">How many digits after the decimal (0–8).</p>
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
                                        value={form.is_base && !hasDefault ? '1' : form.rate_to_default}
                                        onChange={(e) => handleChange('rate_to_default', e.target.value)}
                                        disabled={form.is_base && !hasDefault}
                                        required
                                        aria-invalid={Boolean(errors.rate_to_default)}
                                        className={errors.rate_to_default ? 'border-destructive' : ''}
                                    />
                                    {errors.rate_to_default && <p className="text-destructive text-sm">{errors.rate_to_default}</p>}
                                    <p className="text-muted-foreground text-xs">
                                        {rateDefinition} Enter only a real rate from your market. The default currency is always 1.
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="status">
                                        Status <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.status} onValueChange={(v) => handleChange('status', v as 'active' | 'inactive')}>
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
                                        disabled={!hasDefault}
                                        onChange={(e) => handleChange('is_base', e.target.checked)}
                                        className="h-4 w-4 rounded"
                                    />
                                    <div>
                                        <Label htmlFor="is_base" className="cursor-pointer">
                                            Set as default currency
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            Only one currency can be the default. Changing it does not rewrite historical sales or payments.
                                        </p>
                                    </div>
                                </div>
                                {errors.is_base && <p className="text-destructive mt-2 text-sm">{errors.is_base}</p>}
                            </div>

                            <div className="flex flex-wrap gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Save Currency'}
                                </Button>
                                <BackButton href="/currencies" label="Cancel" />
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
