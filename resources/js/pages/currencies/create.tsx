import { BackButton } from '@/components/form-page';
// resources/js/pages/currencies/create.tsx

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

interface Props {
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currencies', href: '/currencies' },
    { title: 'Create Currency', href: '/currencies/create' },
];

export default function CurrenciesCreate({ errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        code: '',
        name: '',
        symbol: '',
        is_base: false,
        status: 'active' as 'active' | 'inactive',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post('/currencies', form, {
            onSuccess: () => {
                toast({ title: 'Success', description: `Currency "${form.code}" was created successfully.` });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({ title: 'Error', description: 'There was a problem creating the currency.', variant: 'destructive' });
            },
        });
    };

    const handleChange = (field: string, value: string | boolean) => {
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
            <Head title="Create Currency" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/currencies" label="Back" />
                    <div className="flex items-center gap-3">
                        <Coins className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Currency</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Currency Information</CardTitle>
                            <CardDescription>Enter the required information to create a new currency.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {/* Code */}
                                <div className="space-y-2">
                                    <Label>
                                        Currency Code <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.code}
                                        onChange={(e) => handleChange('code', e.target.value.toUpperCase())}
                                        placeholder="e.g. USD, AFN, EUR"
                                        maxLength={10}
                                        className={`font-mono uppercase ${errors.code ? 'border-destructive' : ''}`}
                                    />
                                    {errors.code && <p className="text-destructive text-sm">{errors.code}</p>}
                                    <p className="text-muted-foreground text-xs">3-letter ISO code recommended.</p>
                                </div>

                                {/* Name */}
                                <div className="space-y-2">
                                    <Label>
                                        Currency Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        placeholder="e.g. US Dollar"
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                {/* Symbol */}
                                <div className="space-y-2">
                                    <Label>Symbol</Label>
                                    <Input
                                        value={form.symbol}
                                        onChange={(e) => handleChange('symbol', e.target.value)}
                                        placeholder="e.g. $, AFN, EUR"
                                        maxLength={10}
                                        className={`text-lg ${errors.symbol ? 'border-destructive' : ''}`}
                                    />
                                    {errors.symbol && <p className="text-destructive text-sm">{errors.symbol}</p>}
                                </div>

                                {/* Status */}
                                <div className="space-y-2">
                                    <Label>
                                        Status <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.status} onValueChange={(v) => handleChange('status', v as 'active' | 'inactive')}>
                                        <SelectTrigger className={errors.status ? 'border-destructive' : ''}>
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

                            {/* Base */}
                            <div className="rounded-lg border border-dashed p-4">
                                <div className="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        id="is_base"
                                        checked={form.is_base}
                                        onChange={(e) => handleChange('is_base', e.target.checked)}
                                        className="h-4 w-4 rounded"
                                    />
                                    <div>
                                        <Label htmlFor="is_base" className="cursor-pointer">
                                            Set as Base Currency
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            The base currency is used for financial reporting. Only one currency can be base.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Submit */}
                            <div className="flex gap-2 border-t pt-4">
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
