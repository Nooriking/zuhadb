// resources/js/pages/exchange-rate-history/edit.tsx

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

interface Rate {
    id: number;
    rate_date: string;
    usd_to_afn: string;
    notes: string | null;
}

interface Props {
    rate: Rate;
    errors?: Record<string, string>;
}

export default function ExchangeRateHistoryEdit({ rate, errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        rate_date: rate.rate_date.slice(0, 10),
        usd_to_afn: rate.usd_to_afn,
        notes: rate.notes || '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Exchange Rate History', href: '/exchange-rate-history' },
        { title: 'Edit', href: `/exchange-rate-history/${rate.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.put(`/exchange-rate-history/${rate.id}`, form, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Rate updated.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);
                toast({ title: 'Error', description: 'Could not update the rate.', variant: 'destructive' });
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
            <Head title="Edit Exchange Rate" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <TrendingUp className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Exchange Rate</h1>
                    </div>
                    <Link href="/exchange-rate-history">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Rate Information</CardTitle>
                            <CardDescription>Edit the rate for a specific date.</CardDescription>
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
                                            className={`pl-9 font-mono text-lg ${errors.usd_to_afn ? 'border-destructive' : ''}`}
                                        />
                                    </div>
                                    {errors.usd_to_afn && <p className="text-destructive text-sm">{errors.usd_to_afn}</p>}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Notes (Optional)</Label>
                                <Textarea value={form.notes} onChange={(e) => handleChange('notes', e.target.value)} rows={3} />
                            </div>

                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Update Rate'}
                                </Button>
                                <Link href="/exchange-rate-history">
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
