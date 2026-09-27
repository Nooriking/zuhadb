import { BackButton } from '@/components/form-page';
// resources/js/pages/sales/create.tsx

import { CustomerCombobox } from '@/components/customer-combobox';
import { Badge } from '@/components/ui/badge';
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
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Barcode,
    CheckCircle2,
    CreditCard,
    DollarSign,
    Loader2,
    Package,
    Plus,
    Printer,
    Receipt,
    Save,
    ScanBarcode,
    ShoppingBag,
    Trash2,
    UserPlus,
    Wallet,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { getSwal } from '@/lib/swal';

/* ═══════════════════════════════════════════════════════
 |  Interfaces
 ═══════════════════════════════════════════════════════ */

interface Branch {
    id: number;
    name: string;
    code: string;
}
interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address?: string | null;
    notes?: string | null;
    status?: string;
    current_due_afn?: number | string;
    credit_limit_afn?: number | string;
}
interface Variant {
    id: number;
    product_id: number;
    name: string | null;
    volume_ml: number | null;
    concentration: string | null;
    sale_price_afn: string;
    wholesale_price_afn: string;
    wholesale_min_qty: number;
    purchase_price_afn: string;
    product: {
        id: number;
        name: string;
        brand: { id: number; name: string } | null;
    } | null;
}

interface LineItem {
    _key: string;
    variant_id: string;
    quantity: string;
    sale_price_afn: string;
    discount_afn: string;
}

interface StockError {
    index: number;
    variant_id: number;
    variant_name: string;
    requested: number;
    available: number;
    missing: number;
}

interface Props {
    branches: Branch[];
    customers: Customer[];
    variants: Variant[];
    nextNumber: string;
    supplierCredits?: {
        total: number;
        suppliers: { supplier: string; amount: number; count: number }[];
        items: { id: number; name: string; supplier: string | null; amount: number }[];
    };
    errors?: Record<string, string>;
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const newLine = (): LineItem => ({
    _key: Math.random().toString(36).slice(2),
    variant_id: '',
    quantity: '1',
    sale_price_afn: '',
    discount_afn: '0',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
    { title: 'Create Sale', href: '/sales/create' },
];

function variantLabel(v: Variant): string {
    const parts: string[] = [];
    if (v.product?.brand?.name) parts.push(v.product.brand.name);
    if (v.product?.name) parts.push(v.product.name);
    let spec = '';
    if (v.volume_ml) spec += ` · ${v.volume_ml}ml`;
    if (v.concentration) spec += ` · ${v.concentration.toUpperCase()}`;
    return (parts.join(' — ') || `Variant #${v.id}`) + spec;
}

const formatPrice = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value);

/**
 * ⭐ round2 — گرد کردن به ۲ رقم اعشار
 * برای جلوگیری از خطای شناور مثل 2999.94
 */
const round2 = (value: number): number => {
    return Math.round((value + Number.EPSILON) * 100) / 100;
};

/* ═══════════════════════════════════════════════════════
 |  Main Component
 ═══════════════════════════════════════════════════════ */

export default function SalesCreate({ branches, customers: initialCustomers, variants, nextNumber, supplierCredits, errors: initialErrors }: Props) {
    const { toast } = useToast();

    /* ─── Form State ─── */
    const [form, setForm] = useState({
        branch_id: branches?.[0] ? String(branches[0].id) : '',
        customer_id: 'new' as string,
        sale_date: new Date().toISOString().slice(0, 10),
        payment_method: 'cash' as 'cash' | 'card' | 'bank' | 'mobile' | 'credit' | 'other',
        notes: '',
        discount_afn: '0',
    });

    /* ─── Customer Fields ─── */
    const [customerData, setCustomerData] = useState({
        name: '',
        phone: '',
        address: '',
        notes: '',
    });

    /* ─── Items ─── */
    const [items, setItems] = useState<LineItem[]>([newLine()]);

    /* ─── Payment ─── */
    const [paidAmount, setPaidAmount] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [paymentReference, setPaymentReference] = useState('');
    const [paymentNotes, setPaymentNotes] = useState('');
    const [paidAmountEdited, setPaidAmountEdited] = useState(false);

    /* ─── Barcode ─── */
    const [barcode, setBarcode] = useState('');
    const [barcodeLoading, setBarcodeLoading] = useState(false);
    const [barcodeMessage, setBarcodeMessage] = useState<{
        type: 'success' | 'error';
        text: string;
    } | null>(null);

    const barcodeInputRef = useRef<HTMLInputElement>(null);
    const barcodeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    /* ─── States ─── */
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);
    const [savingCustomer, setSavingCustomer] = useState(false);
    const [customerList, setCustomerList] = useState(initialCustomers);

    /* ─── Created Sale ─── */
    const [createdSale, setCreatedSale] = useState<{
        id: number;
        invoice_number: string;
        receipt_no?: string;
    } | null>(null);

    const isNewCustomer = form.customer_id === 'new';
    const isNoCustomer = form.customer_id === 'none';

    /* ─── Effect: fill customer fields ─── */
    useEffect(() => {
        if (isNewCustomer || isNoCustomer) {
            setCustomerData({ name: '', phone: '', address: '', notes: '' });
            return;
        }
        const selected = customerList.find((c) => String(c.id) === form.customer_id);
        if (selected) {
            setCustomerData({
                name: selected.name || '',
                phone: selected.phone || '',
                address: selected.address || '',
                notes: selected.notes || '',
            });
        }
    }, [form.customer_id, customerList, isNewCustomer, isNoCustomer]);

    /* ─── Focus barcode input on mount ─── */
    useEffect(() => {
        const timer = setTimeout(() => {
            barcodeInputRef.current?.focus();
        }, 300);
        return () => clearTimeout(timer);
    }, []);

    /* ═══════════════════════════════════════════════════════
     |  Selected Customer + Previous Due
     ═══════════════════════════════════════════════════════ */

    const selectedCustomer = useMemo<Customer | null>(() => {
        if (isNewCustomer || isNoCustomer) return null;
        return customerList.find((c) => String(c.id) === form.customer_id) || null;
    }, [form.customer_id, customerList, isNewCustomer, isNoCustomer]);

    const saveCustomer = async () => {
        if (!selectedCustomer) return;
        if (!customerData.name.trim()) {
            setErrors({ 'customer.name': 'Customer name is required.' });
            return;
        }
        setSavingCustomer(true);
        const xsrf = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );
        try {
            const response = await fetch(`/customers/${selectedCustomer.id}`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrf,
                },
                body: JSON.stringify({
                    name: customerData.name,
                    phone: customerData.phone,
                    address: customerData.address,
                    notes: customerData.notes,
                    status: selectedCustomer.status || 'active',
                }),
            });
            const body = await response.json().catch(() => null);
            if (!response.ok) {
                const message = body?.message || body?.errors?.name?.[0] || 'Could not update the customer.';
                setErrors({ 'customer.name': message });
                return;
            }
            setCustomerList((list) =>
                list.map((customer) =>
                    customer.id === selectedCustomer.id
                        ? {
                              ...customer,
                              name: customerData.name,
                              phone: customerData.phone,
                              address: customerData.address,
                              notes: customerData.notes,
                          }
                        : customer,
                ),
            );
            toast({ title: 'Customer updated', description: customerData.name });
        } catch {
            setErrors({ 'customer.name': 'Could not update the customer.' });
        } finally {
            setSavingCustomer(false);
        }
    };

    const previousDueAfn = useMemo(() => {
        if (!selectedCustomer) return 0;
        return Number(selectedCustomer.current_due_afn ?? 0);
    }, [selectedCustomer]);

    const creditLimitAfn = useMemo(() => {
        if (!selectedCustomer) return 0;
        return Number(selectedCustomer.credit_limit_afn ?? 0);
    }, [selectedCustomer]);

    /* ═══════════════════════════════════════════════════════
     |  ⭐ Computed Totals — با round2 دقیق
     ═══════════════════════════════════════════════════════ */

    const totals = useMemo(() => {
        let subtotalAfn = 0;
        let itemCount = 0;
        let totalQty = 0;

        items.forEach((item) => {
            if (!item.variant_id) return;
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.sale_price_afn) || 0;
            const disc = parseFloat(item.discount_afn) || 0;
            subtotalAfn = round2(subtotalAfn + round2(qty * price - disc));
            itemCount++;
            totalQty += qty;
        });

        subtotalAfn = round2(subtotalAfn);

        const discount = round2(parseFloat(form.discount_afn) || 0);
        const totalAfn = round2(Math.max(0, subtotalAfn - discount));

        /* ⭐ Grand Total = Previous Due + New Sale */
        const grandTotalDueAfn = round2(previousDueAfn + totalAfn);

        const paidInput = round2(parseFloat(paidAmount) || 0);
        const paidAfn = round2(Math.min(paidInput, grandTotalDueAfn));
        const remainingDueAfn = round2(Math.max(0, grandTotalDueAfn - paidAfn));

        const newCustomerDueAfterSale = remainingDueAfn;

        const exceedsCreditLimit = creditLimitAfn > 0 && newCustomerDueAfterSale > creditLimitAfn;

        return {
            subtotalAfn,
            discount,
            totalAfn,
            itemCount,
            totalQty,
            previousDueAfn,
            grandTotalDueAfn,
            paidAfn,
            remainingDueAfn,
            newCustomerDueAfterSale,
            exceedsCreditLimit,
        };
    }, [items, form, paidAmount, previousDueAfn, creditLimitAfn]);

    /* ⭐ Auto-fill Paid Amount — با مبلغ فروش فعلی */
    useEffect(() => {
        if (!paidAmountEdited && totals.totalAfn > 0) {
            setPaidAmount(totals.totalAfn.toFixed(2));
        }
    }, [totals.totalAfn, paidAmountEdited]);

    useEffect(() => {
        if (totals.totalAfn <= 0) {
            setPaidAmount('');
            setPaidAmountEdited(false);
        }
    }, [totals.totalAfn]);

    /* ═══════════════════════════════════════════════════════
     |  Item Handlers
     ═══════════════════════════════════════════════════════ */

    const updateItem = (index: number, field: keyof LineItem, value: string) => {
        setItems((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };

            if (field === 'variant_id' && value) {
                const variant = variants.find((v) => v.id === parseInt(value));
                if (variant) {
                    copy[index].sale_price_afn = variant.sale_price_afn || '0';
                }
            }

            return copy;
        });
    };

    const setItemQuantity = (variantId: string, newQty: number) => {
        const qty = Math.max(1, newQty);
        setItems((prev) => prev.map((it) => (it.variant_id === variantId ? { ...it, quantity: String(qty) } : it)));
    };

    const addItem = () => setItems((prev) => [...prev, newLine()]);

    const removeItem = (index: number) => {
        if (items.length === 1) {
            setItems([newLine()]);
        } else {
            setItems((prev) => prev.filter((_, i) => i !== index));
        }
    };

    /* ═══════════════════════════════════════════════════════
     |  Payment Handlers
     ═══════════════════════════════════════════════════════ */

    const fillGrandTotal = () => {
        setPaidAmount(totals.grandTotalDueAfn.toFixed(2));
        setPaidAmountEdited(true);
    };

    const fillSaleOnly = () => {
        setPaidAmount(totals.totalAfn.toFixed(2));
        setPaidAmountEdited(true);
    };

    const setZeroPayment = () => {
        setPaidAmount('0');
        setPaidAmountEdited(true);
    };

    const clearPayment = () => {
        setPaidAmount('');
        setPaidAmountEdited(false);
        setPaymentReference('');
        setPaymentNotes('');
    };

    /* ═══════════════════════════════════════════════════════
     |  Barcode Scanner
     ═══════════════════════════════════════════════════════ */

    const handleBarcodeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setBarcode(value);

        if (barcodeTimerRef.current) clearTimeout(barcodeTimerRef.current);

        if (value.trim().length >= 6) {
            barcodeTimerRef.current = setTimeout(() => {
                performBarcodeLookup(value.trim());
            }, 300);
        }
    };

    const handleBarcodeKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();

        if (barcodeTimerRef.current) {
            clearTimeout(barcodeTimerRef.current);
            barcodeTimerRef.current = null;
        }

        if (barcode.trim().length >= 3) {
            performBarcodeLookup(barcode.trim());
        }
    };

    const performBarcodeLookup = async (code: string) => {
        if (!code || barcodeLoading) return;

        setBarcodeLoading(true);
        setBarcodeMessage(null);

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

            const res = await fetch(`/sales/lookup-barcode?barcode=${encodeURIComponent(code)}`, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                setBarcodeMessage({ type: 'error', text: `Server error (${res.status})` });
                return;
            }

            const data = await res.json();

            if (!data.found) {
                setBarcodeMessage({
                    type: 'error',
                    text: data.message || `Barcode "${code}" not found`,
                });
                setBarcode('');
                return;
            }

            const v = data.variant;
            let wasAlready = false;

            const existingItem = items.find((it) => it.variant_id === String(v.id));

            if (existingItem) {
                wasAlready = true;
                setItemQuantity(String(v.id), (parseInt(existingItem.quantity) || 0) + 1);
            } else {
                setItems((prev) => {
                    const newItem: LineItem = {
                        _key: Math.random().toString(36).slice(2),
                        variant_id: String(v.id),
                        quantity: '1',
                        sale_price_afn: String(v.sale_price_afn || 0),
                        discount_afn: '0',
                    };
                    if (prev.length === 1 && !prev[0].variant_id) return [newItem];
                    return [...prev, newItem];
                });
            }

            const label = [v.product?.brand?.name, v.product?.name, v.volume_ml ? `${v.volume_ml}ml` : null].filter(Boolean).join(' · ');

            setBarcodeMessage({
                type: 'success',
                text: wasAlready ? `✓ Qty +1: ${label}` : `✓ Added: ${label}`,
            });

            setBarcode('');

            setTimeout(() => barcodeInputRef.current?.focus(), 100);
        } catch (err) {
            console.error('❌ Barcode error:', err);
            setBarcodeMessage({ type: 'error', text: 'Connection error. Try again.' });
        } finally {
            setBarcodeLoading(false);
        }
    };

    /* ═══════════════════════════════════════════════════════
     |  SweetAlert برای خطای موجودی
     ═══════════════════════════════════════════════════════ */

    const showStockErrorAlert = (stockErrors: StockError[]) => {
        if (stockErrors.length === 1) {
            const e = stockErrors[0];

            void getSwal().then((Swal) => Swal.fire({
                toast: true,
                position: 'bottom-end',
                icon: 'error',
                title: 'Not enough stock',
                html: `
                    <div style="text-align: right; direction: rtl; font-family: system-ui; font-size: 13px; line-height: 1.7;">
                        <strong style="color:#991b1b;">${e.variant_name}</strong>
                        <div style="margin-top:4px; color:#7f1d1d;">
                            Only <strong style="color:#dc2626;">${e.available}</strong> in stock
                            (requested: <strong>${e.requested}</strong>)
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                width: 'auto',
                padding: '12px 16px',
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', () => { void getSwal().then((S) => S.stopTimer()); });
                    toast.addEventListener('mouseleave', () => { void getSwal().then((S) => S.resumeTimer()); });
                },
            }));
            return;
        }

        const lines = stockErrors
            .map(
                (e) => `
                    <div style="
                        background:#fef2f2;
                        border-right:3px solid #dc2626;
                        padding:6px 10px;
                        margin-bottom:6px;
                        border-radius:4px;
                        text-align:right;
                        direction:rtl;
                    ">
                        <strong style="color:#991b1b;">${e.variant_name}</strong>
                        <div style="font-size:12px; color:#7f1d1d; margin-top:2px;">
                            Stock: <strong style="color:#dc2626;">${e.available}</strong>
                            &nbsp;|&nbsp;
                            Requested: <strong>${e.requested}</strong>
                        </div>
                    </div>
                `,
            )
            .join('');

        void getSwal().then((Swal) => Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon: 'error',
            title: 'Not enough stock for some products',
            html: `<div style="font-family:system-ui; max-height:260px; overflow-y:auto;">${lines}</div>`,
            showConfirmButton: false,
            timer: 7000,
            timerProgressBar: true,
            width: 'auto',
            padding: '12px 16px',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', () => { void getSwal().then((S) => S.stopTimer()); });
                toast.addEventListener('mouseleave', () => { void getSwal().then((S) => S.resumeTimer()); });
            },
        }));
    };

    /* ═══════════════════════════════════════════════════════
     |  ⭐ Submit — با اعتبارسنجی customer_id
     ═══════════════════════════════════════════════════════ */

    const buildPayload = (customerId?: number | string | null) => {
        const payments =
            totals.paidAfn > 0
                ? [
                      {
                          amount_afn: round2(totals.paidAfn), // ⭐ round2
                          payment_method: form.payment_method === 'credit' ? 'cash' : form.payment_method,
                          reference_number: paymentReference || null,
                          notes: paymentNotes || null,
                      },
                  ]
                : [];

        /* ⭐ customer_id — باید عدد معتبر باشد */
        let finalCustomerId: string | null = null;

        if (customerId !== undefined && customerId !== null && String(customerId).trim() !== '') {
            finalCustomerId = String(customerId);
        } else if (form.customer_id && form.customer_id !== 'new' && form.customer_id !== 'none' && form.customer_id.trim() !== '') {
            finalCustomerId = form.customer_id;
        }

        return {
            ...form,
            customer_id: finalCustomerId,
            items: items
                .filter((it) => it.variant_id)
                .map((item) => ({
                    variant_id: parseInt(item.variant_id),
                    quantity: parseInt(item.quantity) || 1,
                    sale_price_afn: round2(parseFloat(item.sale_price_afn) || 0), // ⭐ round2
                    discount_afn: round2(parseFloat(item.discount_afn) || 0), // ⭐ round2
                })),
            payments,
            due_date: totals.remainingDueAfn > 0 ? dueDate || null : null,
        };
    };

    const submitSale = (customerId?: number | string | null) => {
        const payload = buildPayload(customerId);

        console.log('🚀 Submitting sale with payload:', {
            customer_id: payload.customer_id,
            items_count: payload.items.length,
            paid: payload.payments[0]?.amount_afn,
        });

        router.post('/sales', payload, {
            onSuccess: (page) => {
                const flash = (page.props as { flash?: { created_sale?: { id: number; invoice_number: string; receipt_no?: string } } }).flash;
                const sale = flash?.created_sale ?? (page.props as { created_sale?: { id: number; invoice_number: string; receipt_no?: string } }).created_sale;
                if (sale?.id) {
                    setCreatedSale({
                        id: sale.id,
                        invoice_number: sale.invoice_number,
                        receipt_no: sale.receipt_no,
                    });
                    window.open(`/sales/${sale.id}/receipt`, '_blank');
                }
                toast({ title: 'Success', description: 'Sale created successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                const errorsObj = err as Record<string, string>;
                console.error('❌ Sale error:', errorsObj);
                setErrors(errorsObj);
                setLoading(false);

                if (errorsObj.stock_errors) {
                    try {
                        const stockErrors: StockError[] = JSON.parse(errorsObj.stock_errors);
                        showStockErrorAlert(stockErrors);
                        return;
                    } catch (parseErr) {
                        console.error('❌ Failed to parse stock_errors:', parseErr);
                    }
                }

                if (errorsObj.stock) {
                    void getSwal().then((Swal) => Swal.fire({
                        toast: true,
                        position: 'bottom-end',
                        icon: 'error',
                        title: 'Not enough stock',
                        text: errorsObj.stock,
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        width: 'auto',
                        padding: '12px 16px',
                    }));
                    return;
                }

                toast({
                    title: 'Error',
                    description: 'Please check the form fields.',
                    variant: 'destructive',
                });
            },
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});
        setCreatedSale(null);

        if (form.payment_method === 'credit' && (isNoCustomer || form.customer_id === 'none' || !form.customer_id)) {
            setErrors({ customer_id: 'A customer is required when the sale is on credit.' });
            toast({
                title: 'Credit',
                description: 'A customer is required for a credit sale.',
                variant: 'destructive',
            });
            setLoading(false);
            return;
        }

        const underCost = items.find((item) => {
            const variant = variants.find((row) => String(row.id) === item.variant_id);
            const price = parseFloat(item.sale_price_afn) || 0;
            const cost = parseFloat(variant?.purchase_price_afn || '0') || 0;
            return item.variant_id && (price < 0 || price < cost);
        });
        if (underCost) {
            setErrors({ items: 'Sale price cannot be negative or less than the purchase price.' });
            toast({
                title: 'Price',
                description: 'Sale price cannot be negative or less than the purchase price.',
                variant: 'destructive',
            });
            setLoading(false);
            return;
        }

        if (items.every((it) => !it.variant_id)) {
            setErrors({ items: 'At least one product must be added.' });
            toast({
                title: 'Error',
                description: 'Add at least one product.',
                variant: 'destructive',
            });
            setLoading(false);
            return;
        }

        /* ═══════════════════════════════════════════════════════
         |  ⭐ مشتری جدید — ابتدا بساز، سپس ID را بگیر
         ═══════════════════════════════════════════════════════ */

        if (isNewCustomer) {
            if (!customerData.name.trim()) {
                setErrors({ 'customer.name': 'Customer name is required.' });
                toast({
                    title: 'Error',
                    description: 'Customer name is required.',
                    variant: 'destructive',
                });
                setLoading(false);
                return;
            }

            const xsrf = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((row) => row.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );

            fetch('/customers', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrf,
                },
                body: JSON.stringify({
                    name: customerData.name,
                    phone: customerData.phone,
                    address: customerData.address,
                    notes: customerData.notes,
                    status: 'active',
                }),
            })
                .then(async (res) => {
                    const body = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const message =
                            body?.errors?.name?.[0] ||
                            body?.message ||
                            'Could not create the customer.';
                        setErrors({ 'customer.name': message });
                        toast({ title: 'Error', description: message, variant: 'destructive' });
                        setLoading(false);
                        return;
                    }

                    const finalId = body?.customer?.id as number | undefined;
                    if (!finalId) {
                        setErrors({ 'customer.id': 'Customer was created but the id was not returned.' });
                        setLoading(false);
                        return;
                    }

                    submitSale(finalId);
                })
                .catch(() => {
                    setLoading(false);
                    toast({
                        title: 'Error',
                        description: 'Could not create the customer.',
                        variant: 'destructive',
                    });
                });
        } else if (isNoCustomer) {
            /* Walk-in — بدون مشتری */
            submitSale(null);
        } else {
            /* مشتری موجود انتخاب شده */
            submitSale(form.customer_id);
        }
    };

    /* ═══════════════════════════════════════════════════════
     |  Print
     ═══════════════════════════════════════════════════════ */

    const handlePrint = () => {
        if (!createdSale) return;
        router.post(
            `/sales/${createdSale.id}/print-receipt`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    window.open(`/sales/${createdSale.id}/receipt`, '_blank');
                },
            },
        );
    };

    /* ═══════════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Sale" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/sales" label="Back" />
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
                            <ShoppingBag className="text-primary h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Create Sale</h1>
                            <p className="text-muted-foreground font-mono text-sm">{nextNumber}</p>
                        </div>
                    </div>
                </div>

                {/* ═══════════ Errors Panel ═══════════ */}
                {Object.keys(errors).length > 0 && !errors.stock_errors && (
                    <div className="rounded-lg border-2 border-red-400 bg-red-50 p-4">
                        <div className="mb-2 flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-red-600" />
                            <p className="font-bold text-red-800">Validation Errors ({Object.keys(errors).length})</p>
                        </div>
                        <div className="space-y-1 text-xs">
                            {Object.entries(errors)
                                .filter(([key]) => key !== 'stock_errors' && key !== 'stock')
                                .map(([key, value]) => (
                                    <div key={key} className="flex gap-2 rounded bg-white/60 px-2 py-1">
                                        <span className="font-mono font-semibold text-red-700">{key}:</span>
                                        <span className="text-red-600">{value}</span>
                                    </div>
                                ))}
                        </div>
                    </div>
                )}

                {/* ═══════════ Barcode Scanner ═══════════ */}
                <Card className="border-2 border-blue-400 shadow-md">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-full bg-blue-600 text-white shadow">
                                    <ScanBarcode className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">Barcode Scanner</p>
                                    <p className="text-muted-foreground text-xs">Scan or type + Enter (auto after 6 chars)</p>
                                </div>
                            </div>

                            <div className="flex flex-1 gap-2">
                                <div className="relative flex-1">
                                    <Barcode className="text-muted-foreground absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2" />
                                    <Input
                                        ref={barcodeInputRef}
                                        type="text"
                                        value={barcode}
                                        onChange={handleBarcodeChange}
                                        onKeyDown={handleBarcodeKeyDown}
                                        placeholder="Scan or type barcode..."
                                        className="h-12 border-blue-300 bg-white pl-11 font-mono text-lg tracking-widest"
                                        disabled={barcodeLoading}
                                        autoComplete="off"
                                        autoFocus
                                    />
                                </div>
                                <Button
                                    type="button"
                                    onClick={() => performBarcodeLookup(barcode.trim())}
                                    disabled={barcodeLoading || !barcode.trim()}
                                    className="h-12 bg-blue-600 px-6 hover:bg-blue-700"
                                >
                                    {barcodeLoading ? (
                                        <>
                                            <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                            Searching
                                        </>
                                    ) : (
                                        'Add'
                                    )}
                                </Button>
                            </div>

                            <Badge variant="outline" className="gap-1.5 border-green-300 bg-green-50 text-green-700">
                                <span className="h-2 w-2 animate-pulse rounded-full bg-green-500" />
                                Ready
                            </Badge>
                        </div>

                        {barcodeMessage && (
                            <div
                                className={`mt-3 flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium ${
                                    barcodeMessage.type === 'success'
                                        ? 'border-green-300 bg-green-50 text-green-800'
                                        : 'border-red-300 bg-red-50 text-red-800'
                                }`}
                            >
                                <span className="flex-1">{barcodeMessage.text}</span>
                                <button type="button" onClick={() => setBarcodeMessage(null)} className="hover:opacity-70">
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ═══════════ Sale Information ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Receipt className="h-5 w-5" />
                            Sale Information
                        </CardTitle>
                        <CardDescription>Customer, branch and dates.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {branches.length > 1 ? (
                            <div className="space-y-2">
                                <Label>
                                    Branch <span className="text-destructive">*</span>
                                </Label>
                                <Select value={form.branch_id} onValueChange={(v) => setForm({ ...form, branch_id: v })}>
                                    <SelectTrigger className={errors.branch_id ? 'border-destructive' : ''}>
                                        <SelectValue placeholder="Select branch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {branches.map((b) => (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name} ({b.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.branch_id && <p className="text-destructive text-sm">{errors.branch_id}</p>}
                            </div>
                            ) : null}

                            <div className="space-y-2 md:col-span-2">
                                <Label>Customer</Label>
                                <CustomerCombobox
                                    customers={customerList}
                                    value={form.customer_id}
                                    onChange={(v) => setForm({ ...form, customer_id: v })}
                                    error={!!errors.customer_id}
                                />
                                {errors.customer_id && <p className="text-destructive text-sm">{errors.customer_id}</p>}
                            </div>
                        </div>

                        {/* ⭐ Customer Due Info Panel */}
                        {selectedCustomer && (previousDueAfn > 0 || creditLimitAfn > 0) && (
                            <div className="rounded-lg border-2 border-amber-300 bg-amber-50 p-3">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle className="h-4 w-4 text-amber-600" />
                                    <span className="text-sm font-semibold text-amber-900">
                                        {previousDueAfn > 0 ? 'This customer has a previous due' : 'Credit limit'}
                                    </span>
                                </div>
                                <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                                    <span className="text-amber-800">Current due:</span>
                                    <span className="text-right font-mono font-bold text-amber-900">{formatPrice(previousDueAfn)} AFN</span>
                                    {creditLimitAfn > 0 && (
                                        <>
                                            <span className="text-amber-800">Credit limit:</span>
                                            <span className="text-right font-mono text-amber-900">{formatPrice(creditLimitAfn)} AFN</span>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}

                        {form.customer_id !== 'none' && (
                            <div className="space-y-4 rounded-lg border bg-gray-50/50 p-4">
                                <div className="mb-2 flex items-center gap-2">
                                    <UserPlus className="text-primary h-4 w-4" />
                                    <h3 className="text-sm font-semibold">{isNewCustomer ? 'New Customer' : 'Edit customer'}</h3>
                                    {isNewCustomer && <Badge className="ml-auto text-xs">Will be created</Badge>}
                                    {selectedCustomer && (
                                        <Button type="button" variant="outline" size="sm" className="ml-auto" onClick={saveCustomer} disabled={savingCustomer}>
                                            {savingCustomer ? 'Saving...' : 'Save customer'}
                                        </Button>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>
                                            Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            value={customerData.name}
                                            onChange={(e) =>
                                                setCustomerData({
                                                    ...customerData,
                                                    name: e.target.value,
                                                })
                                            }
                                            placeholder="Customer name"
                                            className={errors['customer.name'] ? 'border-destructive' : ''}
                                        />
                                        {errors['customer.name'] && <p className="text-destructive text-sm">{errors['customer.name']}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Phone</Label>
                                        <Input
                                            value={customerData.phone}
                                            onChange={(e) =>
                                                setCustomerData({
                                                    ...customerData,
                                                    phone: e.target.value,
                                                })
                                            }
                                            placeholder="07XX XXX XXX"
                                        />
                                    </div>

                                    <div className="space-y-2 md:col-span-2">
                                        <Label>Address</Label>
                                        <Input
                                            value={customerData.address}
                                            onChange={(e) =>
                                                setCustomerData({
                                                    ...customerData,
                                                    address: e.target.value,
                                                })
                                            }
                                            placeholder="Street, City..."
                                        />
                                    </div>

                                    <div className="space-y-2 md:col-span-2">
                                        <Label>Notes</Label>
                                        <Textarea
                                            value={customerData.notes}
                                            onChange={(e) =>
                                                setCustomerData({
                                                    ...customerData,
                                                    notes: e.target.value,
                                                })
                                            }
                                            placeholder="Optional notes..."
                                            rows={2}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label>
                                    Sale Date <span className="text-destructive">*</span>
                                </Label>
                                <Input type="date" value={form.sale_date} onChange={(e) => setForm({ ...form, sale_date: e.target.value })} />
                            </div>

                            <div className="space-y-2 md:col-span-2">
                                <Label>Notes</Label>
                                <Textarea
                                    value={form.notes}
                                    onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                    rows={1}
                                    placeholder="Optional notes..."
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Items ═══════════ */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Items
                                <Badge variant="secondary">{items.filter((i) => i.variant_id).length}</Badge>
                            </CardTitle>
                        </div>
                        <Button type="button" onClick={addItem} className="gap-2">
                            <Plus className="h-4 w-4" /> Add Item
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {errors.items && <div className="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-700">{errors.items}</div>}

                        {items.map((item, index) => {
                            const qty = parseFloat(item.quantity) || 0;
                            const price = parseFloat(item.sale_price_afn) || 0;
                            const discount = parseFloat(item.discount_afn) || 0;
                            const lineTotal = round2(qty * price - discount); // ⭐ round2

                            const variantInfo = item.variant_id ? variants.find((v) => String(v.id) === item.variant_id) : null;

                            return (
                                <div key={item._key} className="bg-muted/10 rounded-lg border p-3">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-12">
                                        <div className="space-y-1.5 md:col-span-4">
                                            <Label className="text-xs">Product Variant</Label>
                                            <Select value={item.variant_id} onValueChange={(v) => updateItem(index, 'variant_id', v)}>
                                                <SelectTrigger className={errors[`items.${index}.variant_id`] ? 'border-destructive' : ''}>
                                                    <SelectValue placeholder="Select variant">
                                                        {item.variant_id &&
                                                            (() => {
                                                                const v = variants.find((x) => String(x.id) === item.variant_id);
                                                                return v ? <span className="truncate">{variantLabel(v)}</span> : 'Select variant';
                                                            })()}
                                                    </SelectValue>
                                                </SelectTrigger>
                                                <SelectContent className="max-h-[320px]">
                                                    {variants.map((v) => (
                                                        <SelectItem key={v.id} value={String(v.id)}>
                                                            <div className="flex flex-col items-start gap-0.5">
                                                                <span className="font-medium">
                                                                    {v.product?.brand?.name ? `${v.product.brand.name} — ` : ''}
                                                                    {v.product?.name ?? '—'}
                                                                </span>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {[
                                                                        v.volume_ml ? `${v.volume_ml}ml` : null,
                                                                        v.concentration ? v.concentration.toUpperCase() : null,
                                                                        v.name,
                                                                    ]
                                                                        .filter(Boolean)
                                                                        .join(' · ') || `Variant #${v.id}`}
                                                                </span>
                                                            </div>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1.5 md:col-span-2">
                                            <Label className="text-xs">Qty</Label>
                                            <Input
                                                type="number"
                                                min="1"
                                                value={item.quantity}
                                                onChange={(e) => setItemQuantity(item.variant_id, parseInt(e.target.value) || 1)}
                                                className="font-mono"
                                            />
                                        </div>

                                        <div className="space-y-1.5 md:col-span-2">
                                            <Label className="text-xs">
                                                Price (AFN){' '}
                                                {variantInfo && (
                                                    <span className="text-muted-foreground font-mono">
                                                        ({formatPrice(parseFloat(variantInfo.sale_price_afn))})
                                                    </span>
                                                )}
                                            </Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={item.sale_price_afn}
                                                onChange={(e) => updateItem(index, 'sale_price_afn', e.target.value)}
                                                className="font-mono"
                                            />
                                        </div>

                                        <div className="space-y-1.5 md:col-span-2">
                                            <Label className="text-xs">Discount (AFN)</Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={item.discount_afn}
                                                onChange={(e) => updateItem(index, 'discount_afn', e.target.value)}
                                                className="font-mono"
                                            />
                                        </div>

                                        <div className="flex items-end justify-end gap-1 md:col-span-2">
                                            <div className="flex-1 rounded border bg-gray-50 px-2 py-1.5 text-center font-mono text-xs font-semibold">
                                                {formatPrice(lineTotal)}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeItem(index)}
                                                className="text-destructive hover:text-destructive"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                {/* ═══════════ Payment + Totals ═══════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="border-2 border-emerald-300">
                        <CardHeader className="bg-gradient-to-r from-emerald-50 to-white">
                            <div className="flex items-center gap-2">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-white shadow">
                                    <Wallet className="h-5 w-5" />
                                </div>
                                <div className="flex-1">
                                    <CardTitle className="text-lg">Payment</CardTitle>
                                    <CardDescription className="text-xs">
                                        Auto-fills with this sale amount. Change to accept partial payment.
                                    </CardDescription>
                                </div>
                                {!paidAmountEdited && totals.totalAfn > 0 && (
                                    <Badge variant="outline" className="border-emerald-400 bg-emerald-50 text-emerald-700">
                                        ✓ Auto
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-5 pt-4">
                            <div className="space-y-2">
                                <Label>Payment Method</Label>
                                <div className="grid grid-cols-3 gap-2">
                                    {(
                                        [
                                            { value: 'cash', label: 'Cash' },
                                            { value: 'credit', label: 'Credit' },
                                            { value: 'card', label: 'Card' },
                                            { value: 'bank', label: 'Bank' },
                                            { value: 'mobile', label: 'Mobile' },
                                            { value: 'other', label: 'Other' },
                                        ] as const
                                    ).map((m) => (
                                        <button
                                            key={m.value}
                                            type="button"
                                            onClick={() => {
                                                setForm({ ...form, payment_method: m.value });
                                                if (m.value === 'credit') {
                                                    setPaidAmount('0');
                                                    setPaidAmountEdited(true);
                                                }
                                            }}
                                            className={`rounded-lg border-2 p-2.5 text-xs font-medium transition ${
                                                form.payment_method === m.value
                                                    ? 'border-emerald-600 bg-emerald-600 text-white shadow-md'
                                                    : 'border-gray-200 bg-white hover:border-emerald-400'
                                            }`}
                                        >
                                            {m.label}
                                        </button>
                                    ))}
                                </div>
                                {form.payment_method === 'credit' && (
                                    <p className="text-xs text-amber-700">
                                        Credit: this sale is added to the customer balance and no cash payment is recorded.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label className="flex items-center justify-between">
                                    <span>Paid Amount (AFN)</span>
                                    {!paidAmountEdited && totals.totalAfn > 0 && <span className="text-xs font-normal text-emerald-600">auto</span>}
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={paidAmount}
                                    onChange={(e) => {
                                        setPaidAmount(e.target.value);
                                        setPaidAmountEdited(true);
                                    }}
                                    placeholder="0.00"
                                    className="font-mono text-lg"
                                />
                            </div>

                            {totals.remainingDueAfn > 0 && (
                                <div className="space-y-2">
                                    <Label>Pay the rest by</Label>
                                    <Input type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
                                    <p className="text-muted-foreground text-xs">Remaining {formatPrice(totals.remainingDueAfn)} AFN</p>
                                </div>
                            )}

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="text-xs">Reference</Label>
                                    <Input
                                        type="text"
                                        value={paymentReference}
                                        onChange={(e) => setPaymentReference(e.target.value)}
                                        placeholder="TXN-..."
                                        className="font-mono text-sm"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs">Notes</Label>
                                    <Input
                                        type="text"
                                        value={paymentNotes}
                                        onChange={(e) => setPaymentNotes(e.target.value)}
                                        placeholder="Optional..."
                                        className="text-sm"
                                    />
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={fillSaleOnly}
                                    className="gap-1"
                                    disabled={totals.totalAfn <= 0}
                                >
                                    <DollarSign className="h-3.5 w-3.5" />
                                    This Sale ({formatPrice(totals.totalAfn)})
                                </Button>
                                {previousDueAfn > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={fillGrandTotal}
                                        className="gap-1 border-amber-400 text-amber-700"
                                        disabled={totals.grandTotalDueAfn <= 0}
                                    >
                                        <DollarSign className="h-3.5 w-3.5" />
                                        All ({formatPrice(totals.grandTotalDueAfn)})
                                    </Button>
                                )}
                                <Button type="button" variant="outline" size="sm" onClick={setZeroPayment} className="gap-1">
                                    <CreditCard className="h-3.5 w-3.5" />
                                    All Credit
                                </Button>
                                <Button type="button" variant="ghost" size="sm" onClick={clearPayment} className="text-destructive gap-1">
                                    <X className="h-3.5 w-3.5" />
                                    Clear
                                </Button>
                            </div>

                            {/* ⭐ Due Flow Breakdown */}
                            <div className="rounded-lg border-2 border-emerald-300 bg-emerald-50 p-4">
                                <div className="grid grid-cols-2 gap-y-2 text-sm">
                                    {previousDueAfn > 0 && (
                                        <>
                                            <span className="text-gray-600">Previous Due:</span>
                                            <span className="text-right font-mono font-semibold text-amber-700">
                                                {formatPrice(previousDueAfn)} AFN
                                            </span>

                                            <span className="text-gray-600">New Sale:</span>
                                            <span className="text-right font-mono font-semibold">{formatPrice(totals.totalAfn)} AFN</span>

                                            <span className="col-span-2 my-1 border-t border-emerald-300" />
                                        </>
                                    )}

                                    <span className="font-bold text-gray-700">Grand Total:</span>
                                    <span className="text-right font-mono font-bold">{formatPrice(totals.grandTotalDueAfn)} AFN</span>

                                    <span className="text-gray-600">Paid Now:</span>
                                    <span className="text-right font-mono font-semibold text-green-700">{formatPrice(totals.paidAfn)} AFN</span>

                                    <span className="col-span-2 my-1 border-t border-emerald-300" />

                                    <span className="font-bold text-gray-700">Remaining:</span>
                                    <span
                                        className={`text-right font-mono font-bold ${totals.remainingDueAfn > 0 ? 'text-red-600' : 'text-green-600'}`}
                                    >
                                        {formatPrice(totals.remainingDueAfn)} AFN
                                    </span>
                                </div>

                                {totals.exceedsCreditLimit && (
                                    <div className="mt-3 flex items-center gap-2 rounded border border-red-300 bg-red-100 p-2 text-xs text-red-800">
                                        <AlertTriangle className="h-4 w-4 shrink-0" />
                                        <span>Warning: this due exceeds the credit limit ({formatPrice(creditLimitAfn)} AFN).</span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5" />
                                Summary
                            </CardTitle>
                            <CardDescription>Order totals in AFN.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-3 gap-3">
                                <div className="bg-muted/30 rounded-lg border p-3 text-center">
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Items</p>
                                    <p className="text-2xl font-bold">{totals.itemCount}</p>
                                </div>
                                <div className="bg-muted/30 rounded-lg border p-3 text-center">
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Qty</p>
                                    <p className="text-2xl font-bold">{totals.totalQty}</p>
                                </div>
                                <div className="bg-muted/30 rounded-lg border p-3 text-center">
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Curr</p>
                                    <p className="font-mono text-sm font-bold">AFN</p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Header Discount (AFN)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.discount_afn}
                                    onChange={(e) => setForm({ ...form, discount_afn: e.target.value })}
                                    className="font-mono"
                                />
                            </div>

                            <div className="bg-muted/30 rounded-lg border p-4">
                                <div className="grid grid-cols-2 gap-y-3 text-sm">
                                    <span className="text-gray-500">Subtotal:</span>
                                    <span className="text-right font-mono">{formatPrice(totals.subtotalAfn)} AFN</span>

                                    <span className="text-gray-500">Discount:</span>
                                    <span className="text-right font-mono text-red-600">-{formatPrice(totals.discount)} AFN</span>

                                    <span className="col-span-2 my-1 border-t" />

                                    <span className="text-base font-bold">This Sale:</span>
                                    <span className="text-right font-mono text-base font-bold">{formatPrice(totals.totalAfn)} AFN</span>

                                    {previousDueAfn > 0 && (
                                        <>
                                            <span className="text-gray-500">Previous Due:</span>
                                            <span className="text-right font-mono text-amber-600">+{formatPrice(previousDueAfn)} AFN</span>

                                            <span className="col-span-2 my-1 border-t" />

                                            <span className="text-base font-bold">Grand Total:</span>
                                            <span className="text-right font-mono text-base font-bold">
                                                {formatPrice(totals.grandTotalDueAfn)} AFN
                                            </span>
                                        </>
                                    )}

                                    {totals.paidAfn > 0 && (
                                        <>
                                            <span className="col-span-2 my-1 border-t-2" />

                                            <span className="text-gray-500">Paid Now:</span>
                                            <span className="text-right font-mono text-green-600">-{formatPrice(totals.paidAfn)} AFN</span>

                                            <span className="font-bold text-red-600">Remaining:</span>
                                            <span className="text-right font-mono font-bold text-red-600">
                                                {formatPrice(totals.remainingDueAfn)} AFN
                                            </span>
                                        </>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {(supplierCredits?.total || 0) > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Supplier balance</CardTitle>
                            <CardDescription>Open bills you still owe suppliers. This is separate from this sale.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="font-mono text-xl font-bold">{formatPrice(supplierCredits?.total || 0)} AFN</p>
                            {supplierCredits?.suppliers.map((row) => (
                                <div key={row.supplier} className="flex justify-between">
                                    <span>{row.supplier} · {row.count} bills</span>
                                    <span className="font-mono">{formatPrice(row.amount)} AFN</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {createdSale && (
                    <Card className="border-2 border-green-400 bg-green-50">
                        <CardContent className="flex flex-col items-start gap-4 p-4 md:flex-row md:items-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-600">
                                <CheckCircle2 className="h-6 w-6 text-white" />
                            </div>
                            <div className="flex-1">
                                <p className="text-lg font-bold text-green-800">Sale Created Successfully</p>
                                <p className="font-mono text-sm text-green-700">Invoice: {createdSale.invoice_number}</p>
                                {createdSale.receipt_no && <p className="font-mono text-sm text-green-700">Receipt: {createdSale.receipt_no}</p>}
                            </div>
                            <div className="flex gap-2">
                                <Button type="button" onClick={handlePrint} className="gap-2 bg-green-700 hover:bg-green-800">
                                    <Printer className="h-4 w-4" />
                                    Print Receipt
                                </Button>
                                <Link href={`/sales/${createdSale.id}`}>
                                    <Button type="button" variant="outline">
                                        View Sale
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={loading || !!createdSale} className="gap-2" size="lg">
                        <Save className="h-4 w-4" />
                        {loading ? 'Saving...' : 'Save Sale'}
                    </Button>
                    <BackButton href="/sales" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
