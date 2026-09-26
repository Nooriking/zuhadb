import { BackButton } from '@/components/form-page';
// resources/js/pages/exchange-rate-history/create.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, DollarSign, Save, TrendingUp } from 'lucide-react';
import { useState } from 'react';

interface Props {
    latestRate?: string | null;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exchange Rate History', href: '/exchange-rate-history' },
    { title: 'Add Rate', href: '/exchange-rate-history/create' },
];

export default function ExchangeRateHistoryCreate({ latestRate, errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        rate_date: new Date().toISOString().slice(0, 10),
        usd_to_afn: latestRate || '',
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post('/exchange-rate-history', form, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Rate saved.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);
                toast({ title: 'Error', description: 'Could not save the rate.', variant: 'destructive' });
            },
        });
    };

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const n = { ...prev };
                delete n[field];
                return n;
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Exchange Rate" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/exchange-rate-history" label="Back" />
                    <div className="flex items-center gap-3">
                        <TrendingUp className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Add Exchange Rate</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5" />
                                Rate Information
                            </CardTitle>
                            <CardDescription>
                                {latestRate ? `Latest saved rate: ${parseFloat(latestRate).toFixed(4)} AFN` : 'No rate has been saved yet.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>
                                        Rate Date <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="relative">
                                        <Calendar className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            type="date"
                                            value={form.rate_date}
                                            onChange={(e) => handleChange('rate_date', e.target.value)}
                                            className={`pl-9 ${errors.rate_date ? 'border-destructive' : ''}`}
                                        />
                                    </div>
                                    {errors.rate_date && <p className="text-destructive text-sm">{errors.rate_date}</p>}
                                    <p className="text-muted-foreground text-xs">Only one rate is allowed per date.</p>
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        USD to AFN <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="relative">
                                        <DollarSign className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            value={form.usd_to_afn}
                                            onChange={(e) => handleChange('usd_to_afn', e.target.value)}
                                            placeholder="e.g. 69.5000"
                                            className={`pl-9 font-mono text-lg ${errors.usd_to_afn ? 'border-destructive' : ''}`}
                                        />
                                    </div>
                                    {errors.usd_to_afn && <p className="text-destructive text-sm">{errors.usd_to_afn}</p>}
                                    <p className="text-muted-foreground text-xs">Example: 69.5000 means 1 USD = 69.5 AFN.</p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Notes (Optional)</Label>
                                <Textarea
                                    value={form.notes}
                                    onChange={(e) => handleChange('notes', e.target.value)}
                                    placeholder="Optional note..."
                                    rows={3}
                                />
                            </div>

                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Save Rate'}
                                </Button>
                                <BackButton href="/exchange-rate-history" label="Cancel" />
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
