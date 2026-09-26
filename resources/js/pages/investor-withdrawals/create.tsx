import { BackButton } from '@/components/form-page';
﻿// resources/js/pages/investor-withdrawals/create.tsx

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

export default function InvestorWithdrawalsCreate({ investors, selectedInvestorId, nextNumber, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        investor_id: selectedInvestorId ? String(selectedInvestorId) : '',
        investment_id: '',
        type: 'profit' as 'profit' | 'capital',
        amount_afn: '',
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
            amount_afn: parseFloat(form.amount_afn) || 0,
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
                                                    {inv.phone ? ` · ${inv.phone}` : ''}
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

                                {/* Amount */}
                                <div className="space-y-2">
                                    <Label>
                                        Amount (USD) <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={form.amount_afn}
                                        onChange={(e) => handleChange('amount_afn', e.target.value)}
                                        placeholder="0.00"
                                        className={`font-mono ${errors.amount_afn || exceedsBalance ? 'border-destructive' : ''}`}
                                    />
                                    {errors.amount_afn && <p className="text-destructive text-sm">{errors.amount_afn}</p>}
                                    {exceedsBalance && !errors.amount_afn && (
                                        <p className="text-destructive flex items-center gap-1 text-sm">
                                            <AlertTriangle className="h-3 w-3" /> Amount exceeds available balance.
                                        </p>
                                    )}
                                </div>

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
// resources/js/pages/investor-withdrawals/show.tsx

import { Badge } from '@/components/ui/badge';
import { Calendar, CheckCircle2, FileText, User as UserIcon, X, XCircle } from 'lucide-react';

interface Investor {
    id: number;
    full_name: string;
    phone: string | null;
    email: string | null;
}

interface Investment {
    id: number;
    investment_number: string;
}

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface User {
    id: number;
    name: string;
}

interface Withdrawal {
    id: number;
    withdrawal_number: string;
    type: 'profit' | 'capital';
    exchange_rate: string;
    amount_currency: string;
    amount_afn: string;
    balance_before_afn: string;
    balance_after_afn: string;
    withdrawal_date: string;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    notes: string | null;
    approved_at: string | null;
    paid_at: string | null;
    investor: Investor | null;
    investment: Investment | null;
    currency: Currency | null;
    approved_by: User | null;
    paid_by: User | null;
    creator: User | null;
    created_at: string;
}

interface Props {
    withdrawal: Withdrawal;
    errors?: Record<string, string>;
}

const breadcrumbs = (w: Withdrawal): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Withdrawals', href: '/investor-withdrawals' },
    { title: w.withdrawal_number, href: `/investor-withdrawals/${w.id}` },
];

const formatPrice = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num);
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { variant: 'outline' | 'secondary' | 'success' | 'destructive'; label: string }> = {
        pending: { variant: 'outline', label: 'Pending' },
        approved: { variant: 'secondary', label: 'Approved' },
        paid: { variant: 'success', label: 'Paid' },
        rejected: { variant: 'destructive', label: 'Rejected' },
    };
    const item = map[status] || map.pending;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

const getTypeBadge = (type: string) => {
    if (type === 'profit') {
        return (
            <Badge variant="success" className="gap-1">
                <TrendingUp className="h-3 w-3" /> Profit Withdrawal
            </Badge>
        );
    }
    return (
        <Badge variant="secondary" className="gap-1">
            <TrendingDown className="h-3 w-3" /> Capital Withdrawal
        </Badge>
    );
};

export default function InvestorWithdrawalsShow({ withdrawal, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [showRejectModal, setShowRejectModal] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [loading, setLoading] = useState(false);

    const handleApprove = () => {
        if (!confirm('Approve this withdrawal?')) return;

        router.post(
            `/investor-withdrawals/${withdrawal.id}/approve`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Approved', description: 'Withdrawal approved.' }),
                onError: () => toast({ title: 'Error', description: 'Failed to approve.', variant: 'destructive' }),
            },
        );
    };

    const handleMarkPaid = () => {
        if (!confirm(`Mark as paid? This will deduct ${formatPrice(withdrawal.amount_afn)} USD from the investor's ${withdrawal.type} balance.`))
            return;

        router.post(
            `/investor-withdrawals/${withdrawal.id}/mark-paid`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Paid', description: 'Withdrawal marked as paid.' }),
                onError: (errors: any) =>
                    toast({ title: 'Error', description: errors?.message || 'Failed to mark as paid.', variant: 'destructive' }),
            },
        );
    };

    const handleReject = () => {
        setLoading(true);

        router.post(
            `/investor-withdrawals/${withdrawal.id}/reject`,
            { reason: rejectReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({ title: 'Rejected', description: 'Withdrawal rejected.' });
                    setShowRejectModal(false);
                    setLoading(false);
                },
                onError: () => {
                    toast({ title: 'Error', description: 'Failed to reject.', variant: 'destructive' });
                    setLoading(false);
                },
            },
        );
    };

    const isPending = withdrawal.status === 'pending';
    const isApproved = withdrawal.status === 'approved';
    const isPaid = withdrawal.status === 'paid';
    const isRejected = withdrawal.status === 'rejected';

    const formatDateSimple = (date: string | null) => {
        if (!date) return '—';
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(withdrawal)}>
            <Head title={`Withdrawal ${withdrawal.withdrawal_number}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-lg border bg-gray-100">
                            <Banknote className="h-7 w-7 text-gray-600" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="font-mono text-xl font-bold">{withdrawal.withdrawal_number}</h1>
                                {getStatusBadge(withdrawal.status)}
                                {getTypeBadge(withdrawal.type)}
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">Created on {formatDate(withdrawal.created_at)}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {isPending && (
                            <>
                                <Button onClick={handleApprove} className="gap-2 bg-blue-600 hover:bg-blue-700">
                                    <CheckCircle2 className="h-4 w-4" /> Approve
                                </Button>
                                <Button variant="outline" onClick={() => setShowRejectModal(true)} className="text-destructive gap-2">
                                    <XCircle className="h-4 w-4" /> Reject
                                </Button>
                            </>
                        )}
                        {isApproved && (
                            <>
                                <Button onClick={handleMarkPaid} className="gap-2 bg-green-600 hover:bg-green-700">
                                    <DollarSign className="h-4 w-4" /> Mark as Paid
                                </Button>
                                <Button variant="outline" onClick={() => setShowRejectModal(true)} className="text-destructive gap-2">
                                    <XCircle className="h-4 w-4" /> Reject
                                </Button>
                            </>
                        )}
                        <BackButton href="/investor-withdrawals" label="Back" />
                    </div>
                </div>

                {/* Status Alert */}
                {isRejected && (
                    <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
                        <AlertTriangle className="mt-0.5 h-5 w-5 text-red-600" />
                        <div className="text-sm">
                            <p className="font-semibold text-red-800">This withdrawal was rejected.</p>
                            {withdrawal.notes && <p className="mt-1 text-red-700">{withdrawal.notes}</p>}
                        </div>
                    </div>
                )}

                {isPaid && (
                    <div className="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4">
                        <CheckCircle2 className="mt-0.5 h-5 w-5 text-green-600" />
                        <div className="text-sm">
                            <p className="font-semibold text-green-800">This withdrawal has been paid on {formatDateSimple(withdrawal.paid_at)}.</p>
                            <p className="mt-1 text-green-700">Amount deducted from {withdrawal.type} balance.</p>
                        </div>
                    </div>
                )}

                {/* Amount Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-muted-foreground text-xs">Amount</p>
                            <p className="mt-1 font-mono text-2xl font-bold">
                                {formatPrice(withdrawal.amount_afn, 2)} USD
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-muted-foreground text-xs">Balance Before</p>
                            <p className="mt-1 font-mono text-2xl font-bold text-gray-700">{formatPrice(withdrawal.balance_before_afn)} USD</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-muted-foreground text-xs">Balance After</p>
                            <p className="mt-1 font-mono text-2xl font-bold text-blue-600">{formatPrice(withdrawal.balance_after_afn)} USD</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Details */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Withdrawal Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="flex items-start gap-3">
                                    <UserIcon className="text-muted-foreground mt-0.5 h-4 w-4" />
                                    <div>
                                        <p className="text-muted-foreground text-xs">Investor</p>
                                        {withdrawal.investor ? (
                                            <Link href={`/investors/${withdrawal.investor.id}`} className="font-medium text-blue-600 hover:underline">
                                                {withdrawal.investor.full_name}
                                            </Link>
                                        ) : (
                                            <p className="font-medium">—</p>
                                        )}
                                        {withdrawal.investor?.phone && (
                                            <p className="text-muted-foreground text-xs" dir="ltr">
                                                {withdrawal.investor.phone}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Calendar className="text-muted-foreground mt-0.5 h-4 w-4" />
                                    <div>
                                        <p className="text-muted-foreground text-xs">Withdrawal Date</p>
                                        <p className="font-medium">{formatDateSimple(withdrawal.withdrawal_date)}</p>
                                    </div>
                                </div>
                                {withdrawal.investment && (
                                    <div className="flex items-start gap-3">
                                        <FileText className="text-muted-foreground mt-0.5 h-4 w-4" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Related Investment</p>
                                            <Link
                                                href={`/investments/${withdrawal.investment.id}`}
                                                className="font-mono text-sm text-blue-600 hover:underline"
                                            >
                                                {withdrawal.investment.investment_number}
                                            </Link>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {withdrawal.notes && (
                                <>
                                    <div className="border-t" />
                                    <div>
                                        <p className="text-muted-foreground text-xs">Notes</p>
                                        <p className="mt-1 text-sm">{withdrawal.notes}</p>
                                    </div>
                                </>
                            )}

                            <div className="border-t" />

                            {/* Audit Trail */}
                            <div className="space-y-3">
                                <p className="text-sm font-semibold">Audit Trail</p>

                                <div className="space-y-2 text-sm">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Created by</span>
                                        <span className="font-medium">{withdrawal.creator?.name || 'System'}</span>
                                    </div>
                                    <div className="text-muted-foreground text-xs">{formatDate(withdrawal.created_at)}</div>
                                </div>

                                {withdrawal.approved_by && (
                                    <div className="space-y-2 border-t pt-3 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Approved by</span>
                                            <span className="font-medium">{withdrawal.approved_by.name}</span>
                                        </div>
                                        <div className="text-muted-foreground text-xs">{formatDate(withdrawal.approved_at)}</div>
                                    </div>
                                )}

                                {withdrawal.paid_by && (
                                    <div className="space-y-2 border-t pt-3 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Paid by</span>
                                            <span className="font-medium">{withdrawal.paid_by.name}</span>
                                        </div>
                                        <div className="text-muted-foreground text-xs">{formatDate(withdrawal.paid_at)}</div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Status Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <TimelineItem label="Created" date={formatDate(withdrawal.created_at)} active done />
                                <TimelineItem
                                    label="Approved"
                                    date={withdrawal.approved_at ? formatDate(withdrawal.approved_at) : 'Pending'}
                                    active={isApproved || isPaid}
                                    done={isApproved || isPaid}
                                />
                                <TimelineItem
                                    label="Paid"
                                    date={withdrawal.paid_at ? formatDate(withdrawal.paid_at) : 'Pending'}
                                    active={isPaid}
                                    done={isPaid}
                                    isLast
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Reject Modal */}
            {showRejectModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowRejectModal(false)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <XCircle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Reject withdrawal?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Provide a reason for rejecting <strong>{withdrawal.withdrawal_number}</strong>.
                                </p>
                            </div>
                            <button type="button" onClick={() => setShowRejectModal(false)} className="text-muted-foreground hover:text-foreground">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="px-6 pb-4">
                            <Textarea
                                value={rejectReason}
                                onChange={(e) => setRejectReason(e.target.value)}
                                placeholder="Reason for rejection..."
                                rows={3}
                            />
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setShowRejectModal(false)}>
                                Cancel
                            </Button>
                            <Button onClick={handleReject} disabled={loading} className="bg-destructive text-destructive-foreground">
                                {loading ? 'Rejecting...' : 'Reject Withdrawal'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

/* ---------- Timeline Item ---------- */

function TimelineItem({ label, date, active, done, isLast }: { label: string; date: string; active: boolean; done: boolean; isLast?: boolean }) {
    return (
        <div className="flex gap-3">
            <div className="flex flex-col items-center">
                <div
                    className={`flex h-6 w-6 items-center justify-center rounded-full border-2 ${
                        done
                            ? 'border-green-500 bg-green-500 text-white'
                            : active
                              ? 'border-blue-500 bg-blue-500 text-white'
                              : 'border-gray-300 bg-white'
                    }`}
                >
                    {done && <CheckCircle2 className="h-3.5 w-3.5" />}
                </div>
                {!isLast && <div className={`my-1 w-0.5 flex-1 ${done ? 'bg-green-500' : 'bg-gray-200'}`} style={{ minHeight: '24px' }} />}
            </div>
            <div className="pb-4">
                <p className={`text-sm font-medium ${active ? 'text-foreground' : 'text-muted-foreground'}`}>{label}</p>
                <p className="text-muted-foreground text-xs">{date}</p>
            </div>
        </div>
    );
}
