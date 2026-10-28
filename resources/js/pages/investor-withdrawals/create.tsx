import { BackButton } from '@/components/form-page';
import { MoneyField } from '@/components/money-field';
import { defaultCurrencyId, findCurrency, type CatalogCurrency } from '@/lib/currencies';
// resources/js/pages/investor-withdrawals/create.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Banknote, DollarSign, Save, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Investor {
    id: number;
    full_name: string;
    phone: string | null;
    wallet: {
        capital_balance_afn: string;
        profit_balance_afn: string;
    } | null;
}

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface Props {
    investors: Investor[];
    currencies: Currency[];
    selectedInvestorId: number | null;
    nextNumber: string;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Withdrawals', href: '/investor-withdrawals' },
    { title: 'New Withdrawal', href: '/investor-withdrawals/create' },
];

const formatPrice = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num);
};

export default function InvestorWithdrawalsCreate({
    investors,
    currencies = [],
    selectedInvestorId,
    nextNumber,
    errors: initialErrors,
}: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        investor_id: selectedInvestorId ? String(selectedInvestorId) : '',
        investment_id: '',
        type: 'profit' as 'profit' | 'capital',
        amount_afn: '',
        currency_id: defaultCurrencyId(currencies),
        withdrawal_date: new Date().toISOString().slice(0, 10),
        status: 'pending' as 'pending' | 'approved' | 'paid' | 'rejected',
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    // Derived data
    const selectedInvestor = useMemo(() => investors.find((inv) => String(inv.id) === form.investor_id), [investors, form.investor_id]);

    const amountAfn = useMemo(() => parseFloat(form.amount_afn) || 0, [form.amount_afn]);

    const availableBalance = useMemo(() => {
        if (!selectedInvestor?.wallet) return 0;
        return form.type === 'profit'
            ? parseFloat(selectedInvestor.wallet.profit_balance_afn || '0')
            : parseFloat(selectedInvestor.wallet.capital_balance_afn || '0');
    }, [selectedInvestor, form.type]);

    const exceedsBalance = amountAfn > availableBalance;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        if (exceedsBalance) {
            toast({ title: 'Error', description: 'Amount exceeds available balance.', variant: 'destructive' });
            setLoading(false);
            return;
        }

        const payload = {
            ...form,
            investment_id: form.investment_id || null,
            amount: parseFloat(form.amount_afn) || 0,
            amount_afn: parseFloat(form.amount_afn) || 0,
            currency_id: form.currency_id,
        };

        router.post('/investor-withdrawals', payload, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Withdrawal created successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);
                toast({ title: 'Error', description: 'Please check the form fields.', variant: 'destructive' });
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
            <Head title="New Withdrawal" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/investor-withdrawals" label="Back" />
                    <div className="flex items-center gap-3">
                        <Banknote className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">New Withdrawal</h1>
                        <span className="text-muted-foreground font-mono text-sm">{nextNumber}</span>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Form */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Withdrawal Information</CardTitle>
                                <CardDescription>Select investor, type, and amount.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Investor */}
                                <div className="space-y-2">
                                    <Label>
                                        Investor <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.investor_id} onValueChange={(v) => handleChange('investor_id', v)}>
                                        <SelectTrigger className={errors.investor_id ? 'border-destructive' : ''}>
                                            <SelectValue placeholder="Select investor" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {investors.map((inv) => (
                                                <SelectItem key={inv.id} value={String(inv.id)}>
                                                    {inv.full_name}
                                                    {inv.phone ? ` Â· ${inv.phone}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.investor_id && <p className="text-destructive text-sm">{errors.investor_id}</p>}
                                </div>

                                {/* Type */}
                                <div className="space-y-2">
                                    <Label>
                                        Withdrawal Type <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            onClick={() => handleChange('type', 'profit')}
                                            className={`flex items-center gap-2 rounded-lg border-2 p-3 transition-colors ${
                                                form.type === 'profit'
                                                    ? 'border-green-500 bg-green-50 text-green-800'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        >
                                            <TrendingUp className="h-5 w-5" />
                                            <div className="text-left">
                                                <p className="text-sm font-semibold">Profit</p>
                                                <p className="text-xs opacity-75">From earned profit</p>
                                            </div>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => handleChange('type', 'capital')}
                                            className={`flex items-center gap-2 rounded-lg border-2 p-3 transition-colors ${
                                                form.type === 'capital'
                                                    ? 'border-blue-500 bg-blue-50 text-blue-800'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        >
                                            <TrendingDown className="h-5 w-5" />
                                            <div className="text-left">
                                                <p className="text-sm font-semibold">Capital</p>
                                                <p className="text-xs opacity-75">From invested capital</p>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        Withdrawal Date <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="date"
                                        value={form.withdrawal_date}
                                        onChange={(e) => handleChange('withdrawal_date', e.target.value)}
                                    />
                                </div>

                                <MoneyField
                                    label="Amount"
                                    required
                                    amount={form.amount_afn}
                                    currencyId={form.currency_id}
                                    currencies={currencies}
                                    onAmountChange={(val) => handleChange('amount_afn', val)}
                                    onCurrencyChange={(val) => handleChange('currency_id', val)}
                                    error={errors.amount_afn || errors.amount || errors.currency_id}
                                />
                                {exceedsBalance && !errors.amount_afn && (
                                    <p className="text-destructive flex items-center gap-1 text-sm">
                                        <AlertTriangle className="h-3 w-3" /> Amount exceeds available ledger balance (AFN). Converted amount is
                                        checked on the server.
                                    </p>
                                )}

                                {/* Notes */}
                                <div className="space-y-2">
                                    <Label>Notes</Label>
                                    <Textarea
                                        value={form.notes}
                                        onChange={(e) => handleChange('notes', e.target.value)}
                                        rows={3}
                                        placeholder="Optional notes about this withdrawal..."
                                    />
                                </div>

                                {/* Status */}
                                <div className="space-y-2">
                                    <Label>Initial Status</Label>
                                    <Select value={form.status} onValueChange={(v) => handleChange('status', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="approved">Approved</SelectItem>
                                            <SelectItem value="paid">Paid</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-xs">
                                        Use "pending" for normal flow. Balance is only deducted when marked as paid.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar: Investor Balance */}
                    <div className="space-y-4">
                        {selectedInvestor?.wallet ? (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Wallet className="h-4 w-4" />
                                            Available Balance
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <p className="text-muted-foreground text-xs">Capital</p>
                                            <p className="font-mono text-2xl font-bold text-blue-600">
                                                {formatPrice(selectedInvestor.wallet.capital_balance_afn)} USD
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs">Profit</p>
                                            <p className="font-mono text-2xl font-bold text-green-600">
                                                {formatPrice(selectedInvestor.wallet.profit_balance_afn)} USD
                                            </p>
                                        </div>
                                        <div className="border-t pt-3">
                                            <p className="text-muted-foreground text-xs">Selected for withdrawal</p>
                                            <p className="font-mono text-lg font-bold">{formatPrice(availableBalance)} USD</p>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="bg-blue-50">
                                    <CardContent className="pt-6">
                                        <div className="flex items-start gap-3">
                                            <DollarSign className="mt-0.5 h-5 w-5 text-blue-600" />
                                            <div className="text-sm">
                                                <p className="font-semibold text-blue-900">After this withdrawal</p>
                                                <p className="mt-1 font-mono text-lg font-bold text-blue-700">
                                                    {formatPrice(Math.max(0, availableBalance - amountAfn))} USD
                                                </p>
                                                <p className="mt-1 text-xs text-blue-700">Remaining in {form.type} balance</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </>
                        ) : (
                            <Card>
                                <CardContent className="pt-6">
                                    <p className="text-muted-foreground text-center text-sm">Select an investor to see their balance.</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                {/* Submit */}
                <div className="flex gap-2">
                    <Button type="submit" disabled={loading || exceedsBalance} className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Saving...' : 'Create Withdrawal'}
                    </Button>
                    <BackButton href="/investor-withdrawals" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
