// resources/js/pages/investors/create.tsx

import { BackButton } from '@/components/form-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, CircleDollarSign, Package, Save, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string | null;
}

interface Props {
    users: User[];
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
    { title: 'Create Investor', href: '/investors/create' },
];

const money = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value || 0);

export default function InvestorsCreate({ users, errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        user_id: 'none',
        full_name: '',
        phone: '',
        email: '',
        address: '',
        notes: '',
        status: 'active' as 'active' | 'inactive',
        source_type: 'cash' as 'cash' | 'goods',
        capital_amount: '',
        exchange_rate: '',
        estimated_goods_value_afn: '',
    });

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    // ✅ ریست user_id اگر لیست خالی شد
    useEffect(() => {
        if (users.length === 0 && form.user_id !== 'none') {
            setForm((prev) => ({ ...prev, user_id: 'none' }));
        }
    }, [users.length]);

    /* ═══════════ محاسبات زنده ═══════════ */
    const isCash = form.source_type === 'cash';
    const capitalNum = parseFloat(form.capital_amount) || 0;
    const rateNum = parseFloat(form.exchange_rate) || 0;
    const estimatedGoodsValueNum = parseFloat(form.estimated_goods_value_afn) || 0;
    const capitalInUsd = rateNum > 0 ? capitalNum / rateNum : 0;

    // ✅ بررسی اینکه یوزر انتخاب‌شده هنوز معتبر است (در لیست موجود است)
    const isSelectedUserValid = form.user_id === 'none' || users.some((u) => String(u.id) === form.user_id);

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const copy = { ...prev };
                delete copy[field];
                return copy;
            });
        }
    };

    /* ═══════════ تغییر نوع سرمایه‌گذاری ═══════════ */
    const setSourceType = (type: 'cash' | 'goods') => {
        setForm((prev) => ({
            ...prev,
            source_type: type,
            capital_amount: type === 'goods' ? '' : prev.capital_amount,
            exchange_rate: type === 'goods' ? '' : prev.exchange_rate,
            estimated_goods_value_afn: type === 'cash' ? '' : prev.estimated_goods_value_afn,
        }));
    };

    /* ═══════════ Submit ═══════════ */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const clientErrors: Record<string, string> = {};

        if (!form.full_name.trim()) {
            clientErrors.full_name = 'Full name is required.';
        }

        // ✅ بررسی نهایی: آیا user_id انتخاب‌شده هنوز معتبر است؟
        if (form.user_id !== 'none' && !isSelectedUserValid) {
            clientErrors.user_id = 'This user is already linked to another investor.';
        }

        /* ─── حالت نقدی ─── */
        if (isCash) {
            if (capitalNum <= 0) {
                clientErrors.capital_amount = 'Capital amount is required for cash investors.';
            }
            if (capitalNum > 0 && rateNum <= 0) {
                clientErrors.exchange_rate = 'Exchange rate is required when capital is entered.';
            }
        }

        /* ─── حالت جنسی ─── */
        if (!isCash) {
            if (estimatedGoodsValueNum <= 0) {
                clientErrors.estimated_goods_value_afn = 'Estimated goods value is required for goods investors.';
            }
        }

        if (Object.keys(clientErrors).length > 0) {
            setErrors(clientErrors);
            setLoading(false);
            toast({
                title: 'Validation Error',
                description: Object.values(clientErrors)[0],
                variant: 'destructive',
            });
            return;
        }

        const payload = {
            user_id: form.user_id === 'none' ? '' : form.user_id,
            full_name: form.full_name,
            phone: form.phone,
            email: form.email,
            address: form.address,
            notes: form.notes,
            status: form.status,
            source_type: form.source_type,
            capital_amount: isCash && form.capital_amount !== '' ? form.capital_amount : '0',
            exchange_rate: isCash && form.exchange_rate !== '' ? form.exchange_rate : '0',
            estimated_goods_value_afn: !isCash && form.estimated_goods_value_afn !== '' ? form.estimated_goods_value_afn : '0',
        };

        router.post('/investors', payload, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Investor created successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                const errorObj = err as Record<string, string>;
                setErrors(errorObj);
                setLoading(false);

                // ✅ اگر خطای user_id بود، مقدار انتخاب‌شده را پاک کن
                if (errorObj.user_id) {
                    setForm((prev) => ({ ...prev, user_id: 'none' }));
                }

                toast({
                    title: 'Error',
                    description: errorObj.user_id || 'Please check the form fields.',
                    variant: 'destructive',
                });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Investor" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/investors" label="Back" />
                    <div className="flex items-center gap-3">
                        <UserPlus className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Investor</h1>
                    </div>
                </div>

                {/* ═══════════ Investor Information ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle>Investor Information</CardTitle>
                        <CardDescription>Basic info about the investor.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Link to User */}
                        <div className="rounded-lg border border-dashed p-4">
                            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                                <Users className="h-4 w-4" /> Link to User Account (optional)
                            </h3>
                            <div className="space-y-2">
                                <Label>User Account</Label>
                                <Select
                                    value={form.user_id}
                                    onValueChange={(v) => handleChange('user_id', v)}
                                    disabled={users.length === 0} // ✅ غیرفعال وقتی لیست خالی است
                                >
                                    <SelectTrigger className={errors.user_id ? 'border-destructive' : ''}>
                                        <SelectValue placeholder="No linked user" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No linked user</SelectItem>
                                        {users.length === 0 ? (
                                            <div className="text-muted-foreground px-3 py-2 text-sm">
                                                No available users. All investor users are already linked.
                                            </div>
                                        ) : (
                                            users.map((u) => (
                                                <SelectItem key={u.id} value={String(u.id)}>
                                                    {u.name}
                                                    {u.email ? ` (${u.email})` : ''}
                                                </SelectItem>
                                            ))
                                        )}
                                    </SelectContent>
                                </Select>

                                {errors.user_id && <p className="text-destructive text-sm">{errors.user_id}</p>}

                                <p className="text-muted-foreground text-xs">
                                    Only users with the "Investor" role who are <strong>not already linked</strong> to another investor are shown.
                                    This lets the investor log in and view their own data.
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>
                                    Full Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    type="text"
                                    value={form.full_name}
                                    onChange={(e) => handleChange('full_name', e.target.value)}
                                    placeholder="e.g. Ahmad Karimi"
                                    className={errors.full_name ? 'border-destructive' : ''}
                                />
                                {errors.full_name && <p className="text-destructive text-sm">{errors.full_name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Phone</Label>
                                <Input
                                    type="text"
                                    value={form.phone}
                                    onChange={(e) => handleChange('phone', e.target.value)}
                                    placeholder="+93 70 123 4567"
                                    dir="ltr"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) => handleChange('email', e.target.value)}
                                    placeholder="investor@example.com"
                                    className={errors.email ? 'border-destructive' : ''}
                                />
                                {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select value={form.status} onValueChange={(v) => handleChange('status', v)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Address</Label>
                            <Textarea
                                value={form.address}
                                onChange={(e) => handleChange('address', e.target.value)}
                                rows={3}
                                placeholder="Full address..."
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Notes</Label>
                            <Textarea
                                value={form.notes}
                                onChange={(e) => handleChange('notes', e.target.value)}
                                rows={3}
                                placeholder="Any extra notes..."
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ ✅ Investment Type ═══════════ */}
                <Card className={`border-l-4 ${isCash ? 'border-l-emerald-500' : 'border-l-blue-500'}`}>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {isCash ? (
                                <>
                                    <CircleDollarSign className="h-5 w-5 text-emerald-600" />
                                    Cash Investor
                                </>
                            ) : (
                                <>
                                    <Package className="h-5 w-5 text-blue-600" />
                                    Goods Investor
                                </>
                            )}
                        </CardTitle>
                        <CardDescription>Choose how this investor is contributing to the business.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* ✅ انتخاب نوع */}
                        <div className="grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                onClick={() => setSourceType('cash')}
                                className={`rounded-xl border-2 p-4 text-left transition ${
                                    isCash ? 'border-emerald-500 bg-emerald-50' : 'border-border hover:border-emerald-300'
                                }`}
                            >
                                <div className="mb-2 flex items-center gap-2 font-semibold text-emerald-700">
                                    <CircleDollarSign className="h-5 w-5" /> Cash
                                </div>
                                <p className="text-muted-foreground text-sm">Investor deposits cash. An investment pot will be created for them.</p>
                            </button>

                            <button
                                type="button"
                                onClick={() => setSourceType('goods')}
                                className={`rounded-xl border-2 p-4 text-left transition ${
                                    !isCash ? 'border-blue-500 bg-blue-50' : 'border-border hover:border-blue-300'
                                }`}
                            >
                                <div className="mb-2 flex items-center gap-2 font-semibold text-blue-700">
                                    <Package className="h-5 w-5" /> Goods
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    Investor gives perfumes instead of cash. Their goods will be recorded in Add Stock.
                                </p>
                            </button>
                        </div>

                        {/* ✅ Info Banner */}
                        <div
                            className={`flex items-start gap-3 rounded-lg border-2 p-4 ${
                                isCash ? 'border-emerald-300 bg-emerald-50' : 'border-blue-300 bg-blue-50'
                            }`}
                        >
                            <AlertCircle className={`mt-0.5 h-5 w-5 shrink-0 ${isCash ? 'text-emerald-600' : 'text-blue-600'}`} />
                            <div className="text-sm">
                                <p className={`font-semibold ${isCash ? 'text-emerald-800' : 'text-blue-800'}`}>
                                    {isCash ? '💵 Cash Investor' : '📦 Goods Investor (No Cash)'}
                                </p>
                                <p className={`mt-0.5 ${isCash ? 'text-emerald-700' : 'text-blue-700'}`}>
                                    {isCash
                                        ? 'This investor deposits cash. Enter the amount below.'
                                        : 'This investor brings goods instead of cash. Enter the estimated value of the goods below — the detailed goods will be recorded in Add Stock.'}
                                </p>
                            </div>
                        </div>

                        {/* ═══════════ حالت نقدی ═══════════ */}
                        {isCash && (
                            <>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {/* Capital Amount */}
                                    <div className="space-y-2">
                                        <Label>
                                            Capital Amount (AFN) <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.capital_amount}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                if (val !== '' && parseFloat(val) < 0) return;
                                                handleChange('capital_amount', val);
                                            }}
                                            placeholder="e.g. 65000"
                                            className={errors.capital_amount ? 'border-destructive' : ''}
                                            dir="ltr"
                                            required={isCash}
                                        />
                                        {errors.capital_amount && <p className="text-destructive text-sm">{errors.capital_amount}</p>}
                                        <p className="text-muted-foreground text-xs">The initial cash amount this investor is depositing.</p>
                                    </div>

                                    {/* Exchange Rate */}
                                    <div className="space-y-2">
                                        <Label>
                                            Exchange Rate (USD → AFN) <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={form.exchange_rate}
                                            onChange={(e) => handleChange('exchange_rate', e.target.value)}
                                            placeholder="e.g. 64"
                                            className={errors.exchange_rate ? 'border-destructive' : ''}
                                            dir="ltr"
                                            required={isCash && capitalNum > 0}
                                        />
                                        {errors.exchange_rate && <p className="text-destructive text-sm">{errors.exchange_rate}</p>}
                                        <p className="text-muted-foreground text-xs">Rate used to convert USD to AFN.</p>
                                    </div>
                                </div>

                                {/* Live Preview */}
                                {capitalNum > 0 && rateNum > 0 && (
                                    <div className="bg-muted/30 rounded-lg border p-4">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <p className="text-muted-foreground text-xs uppercase">Capital (AFN)</p>
                                                <p className="font-mono text-lg font-semibold">{money(capitalNum)} AFN</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-xs uppercase">Capital (USD)</p>
                                                <p className="font-mono text-lg font-semibold text-emerald-700">${money(capitalInUsd)}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-xs uppercase">Rate Used</p>
                                                <p className="font-mono text-lg font-semibold">{money(rateNum)} AFN/USD</p>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* ═══════════ حالت جنسی ═══════════ */}
                        {!isCash && (
                            <>
                                {/* Estimated Goods Value */}
                                <div className="space-y-2">
                                    <Label>
                                        Estimated Goods Value (AFN) <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.estimated_goods_value_afn}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            if (val !== '' && parseFloat(val) < 0) return;
                                            handleChange('estimated_goods_value_afn', val);
                                        }}
                                        placeholder="e.g. 80000"
                                        className={errors.estimated_goods_value_afn ? 'border-destructive' : ''}
                                        dir="ltr"
                                        required={!isCash}
                                    />
                                    {errors.estimated_goods_value_afn && (
                                        <p className="text-destructive text-sm">{errors.estimated_goods_value_afn}</p>
                                    )}
                                    <p className="text-muted-foreground text-xs">
                                        The estimated total value of the goods this investor is providing. This is for reference — the actual goods
                                        will be recorded in Add Stock.
                                    </p>
                                </div>

                                {/* راهنما برای Goods */}
                                <div className="rounded-lg border border-dashed border-blue-300 bg-blue-50/50 p-4">
                                    <p className="text-sm text-blue-800">
                                        <strong>Goods Investor Mode:</strong> No cash investment pot will be created.
                                        <br />
                                        👉 To record the actual goods this investor brings, go to{' '}
                                        <span className="rounded bg-blue-100 px-1.5 py-0.5 font-mono text-xs">Products → Add Stock</span> and select
                                        this investor as the funding source (leave the investment pot empty).
                                    </p>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* ═══════════ Actions ═══════════ */}
                <div className="flex gap-2">
                    <Button type="submit" disabled={loading} className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Saving...' : 'Save Investor'}
                    </Button>
                    <BackButton href="/investors" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
