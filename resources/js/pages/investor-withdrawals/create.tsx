import { BackButton } from '@/components/form-page';
import { MoneyField } from '@/components/money-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useExchange } from '@/hooks/useExchange';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { catalogEquivalents, resolveCatalogFxRates } from '@/lib/currency';
import { defaultCurrencyId, findCurrency, type CatalogCurrency } from '@/lib/currencies';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Banknote, DollarSign, Save, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Investor {
    id: number;
    full_name: string;
    phone: string | null;
    wallet: {
        capital_balance_afn: string;
        profit_balance_afn: string;
        by_currency?: Array<{
            currency_code: string;
            capital_balance_currency: number;
            capital_balance_afn: number;
            profit_balance_currency: number;
            profit_balance_afn: number;
        }>;
    } | null;
}

interface Props {
    investors: Investor[];
    currencies: CatalogCurrency[];
    selectedInvestorId: number | null;
    nextNumber: string;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Withdrawals', href: '/investor-withdrawals' },
    { title: 'New Withdrawal', href: '/investor-withdrawals/create' },
];

const formatPrice = (value: string | number, digits: number = 2) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number.isFinite(num) ? num : 0);
};

export default function InvestorWithdrawalsCreate({
    investors,
    currencies = [],
    selectedInvestorId,
    nextNumber,
    errors: initialErrors,
}: Props) {
    const { toast } = useToast();
    const exchange = useExchange();
    const [form, setForm] = useState({
        investor_id: selectedInvestorId ? String(selectedInvestorId) : '',
        investment_id: '',
        type: 'profit' as 'profit' | 'capital',
        amount: '',
        currency_id: defaultCurrencyId(currencies),
        withdrawal_date: new Date().toISOString().slice(0, 10),
        status: 'pending' as 'pending' | 'approved' | 'paid',
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const selectedInvestor = useMemo(
        () => investors.find((inv) => String(inv.id) === form.investor_id),
        [investors, form.investor_id],
    );

    const selectedCurrency = useMemo(
        () => findCurrency(currencies, form.currency_id),
        [currencies, form.currency_id],
    );

    const availableAfn = useMemo(() => {
        if (!selectedInvestor?.wallet) return 0;
        return form.type === 'profit'
            ? parseFloat(selectedInvestor.wallet.profit_balance_afn || '0')
            : parseFloat(selectedInvestor.wallet.capital_balance_afn || '0');
    }, [selectedInvestor, form.type]);

    const rates = useMemo(
        () =>
            resolveCatalogFxRates({
                boardUsdToAfn: exchange.usdToAfn || 0,
                boardAedToAfn: exchange.aedToAfn || 0,
            }),
        [exchange.usdToAfn, exchange.aedToAfn],
    );

    const amountEntered = useMemo(() => parseFloat(form.amount) || 0, [form.amount]);

    const estimatedAfn = useMemo(() => {
        if (!selectedCurrency || amountEntered <= 0) return 0;
        if (!rates) {
            return selectedCurrency.code === 'AFN' ? amountEntered : 0;
        }
        const eq = catalogEquivalents(amountEntered, selectedCurrency.code, rates);
        return eq?.afn ?? 0;
    }, [amountEntered, selectedCurrency, rates]);

    const remainingAfn = Math.max(0, availableAfn - estimatedAfn);
    const exceedsBalance = estimatedAfn > availableAfn + 0.01;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (loading) return;
        setLoading(true);
        setErrors({});

        if (exceedsBalance) {
            toast({
                title: 'Error',
                description: 'AFN equivalent exceeds available balance.',
                variant: 'destructive',
            });
            setLoading(false);
            return;
        }

        router.post(
            '/investor-withdrawals',
            {
                ...form,
                investment_id: form.investment_id || null,
                amount: amountEntered,
                currency_id: form.currency_id,
            },
            {
                onSuccess: () => {
                    toast({ title: 'Success', description: 'Withdrawal created successfully.' });
                    setLoading(false);
                },
                onError: (err) => {
                    setErrors(err as Record<string, string>);
                    setLoading(false);
                    toast({ title: 'Error', description: 'Please check the form fields.', variant: 'destructive' });
                },
                onFinish: () => setLoading(false),
            },
        );
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
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Withdrawal Information</CardTitle>
                                <CardDescription>
                                    Enter the amount in the currency paid to the investor. Available profit is tracked in AFN.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
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
                                                    {inv.phone ? ` · ${inv.phone}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.investor_id && <p className="text-destructive text-sm">{errors.investor_id}</p>}
                                </div>

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
                                    label="Withdrawal amount"
                                    required
                                    amount={form.amount}
                                    currencyId={form.currency_id}
                                    currencies={currencies}
                                    onAmountChange={(val) => handleChange('amount', val)}
                                    onCurrencyChange={(val) => handleChange('currency_id', val)}
                                    error={errors.amount || errors.currency_id}
                                />

                                {amountEntered > 0 && (
                                    <div className="bg-muted/40 space-y-1 rounded-md px-3 py-2 text-xs">
                                        <p className="font-medium">Ledger preview (authoritative at save)</p>
                                        <p className="font-mono">
                                            ≈ {formatPrice(estimatedAfn)} AFN will be deducted from available {form.type}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Remaining after: {formatPrice(remainingAfn)} AFN
                                        </p>
                                    </div>
                                )}

                                {exceedsBalance && !errors.amount && (
                                    <p className="text-destructive flex items-center gap-1 text-sm">
                                        <AlertTriangle className="h-3 w-3" /> AFN equivalent exceeds available {form.type}{' '}
                                        balance.
                                    </p>
                                )}

                                <div className="space-y-2">
                                    <Label>Notes</Label>
                                    <Textarea
                                        value={form.notes}
                                        onChange={(e) => handleChange('notes', e.target.value)}
                                        rows={3}
                                        placeholder="Optional notes about this withdrawal..."
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Initial Status</Label>
                                    <Select value={form.status} onValueChange={(v) => handleChange('status', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="approved">Approved</SelectItem>
                                            <SelectItem value="paid">Paid (deduct immediately)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-xs">
                                        Balance is deducted only when status is paid.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

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
                                            <p className="text-muted-foreground text-xs">Capital (AFN ledger)</p>
                                            <p className="font-mono text-2xl font-bold text-blue-600">
                                                {formatPrice(selectedInvestor.wallet.capital_balance_afn)} AFN
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs">Profit (AFN ledger)</p>
                                            <p className="font-mono text-2xl font-bold text-green-600">
                                                {formatPrice(selectedInvestor.wallet.profit_balance_afn)} AFN
                                            </p>
                                        </div>
                                        <div className="border-t pt-3">
                                            <p className="text-muted-foreground text-xs">Selected for withdrawal</p>
                                            <p className="font-mono text-lg font-bold">{formatPrice(availableAfn)} AFN</p>
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
                                                    {formatPrice(remainingAfn)} AFN
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
                                    <p className="text-muted-foreground text-center text-sm">
                                        Select an investor to see their balance.
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

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
