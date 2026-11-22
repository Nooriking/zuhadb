// resources/js/pages/products/create.tsx

import { BackButton } from '@/components/form-page';
import { MoneyField } from '@/components/money-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useExchange } from '@/hooks/useExchange';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { defaultCurrencyId, findCurrency, type CatalogCurrency } from '@/lib/currencies';
import { calcProfits, estimateToAfn, formatEstimate as fmt } from '@/lib/product-estimate';
import { getSwal } from '@/lib/swal';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRightLeft,
    Barcode,
    CheckCircle2,
    Package,
    Plus,
    Receipt,
    Save,
    Scan,
    Star,
    Tag,
    Trash2,
    TrendingUp,
    User,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Brand { id: number; name: string; }
interface Investor { id: number; name: string; }
interface Supplier { id: number; name: string; }

interface BarcodeItem {
    barcode: string;
    barcode_type: string;
    is_primary: boolean;
    current_stock: string;
    _checking?: boolean;
    _exists?: boolean;
    _existingProduct?: string | null;
}

interface Variant {
    _key: string;
    volume_ml: string;
    concentration: string;
    purchase_price_afn: string;
    purchase_currency_id: string;
    sale_price_afn: string;
    sale_currency_id: string;
    wholesale_price_afn: string;
    wholesale_currency_id: string;
    wholesale_min_qty: string;
    barcodes: BarcodeItem[];
}

interface Purchase {
    _key: string;
    variant_index: number;
    investor_id: string;
    supplier_id: string;
    quantity: string;
    profit_per_unit: string;
    profit_currency_id: string;
    invoice_number: string;
    purchase_date: string;
    payment_status: string;
    paid_now: string;
    notes: string;
    billImage?: File | null;
}

interface Props {
    brands: Brand[];
    investors?: Investor[];
    suppliers?: Supplier[];
    currencies?: CatalogCurrency[];
    errors?: Record<string, string>;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Products', href: '/products' },
    { title: 'Create Product', href: '/products/create' },
];

const CONCENTRATIONS = [
    { value: 'parfum', label: 'Parfum' },
    { value: 'edp', label: 'Eau de Parfum (EDP)' },
    { value: 'edt', label: 'Eau de Toilette (EDT)' },
    { value: 'edc', label: 'Eau de Cologne (EDC)' },
    { value: 'edt_legere', label: 'EDT Légère' },
    { value: 'body_mist', label: 'Body Mist' },
    { value: 'attar', label: 'Attar' },
    { value: 'oud', label: 'Oud' },
    { value: 'other', label: 'Other' },
];

const BARCODE_TYPES = [
    { value: 'EAN13', label: 'EAN-13' },
    { value: 'EAN8', label: 'EAN-8' },
    { value: 'UPC', label: 'UPC' },
    { value: 'CODE128', label: 'Code 128' },
    { value: 'CODE39', label: 'Code 39' },
    { value: 'QR', label: 'QR' },
    { value: 'other', label: 'Other' },
];

const PAYMENT_STATUSES = [
    { value: 'paid', label: 'Paid' },
    { value: 'credit', label: 'Credit' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'partial', label: 'Partial' },
];

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const today = () => new Date().toISOString().slice(0, 10);

const newBarcode = (isPrimary: boolean): BarcodeItem => ({
    barcode: '',
    barcode_type: 'EAN13',
    is_primary: isPrimary,
    current_stock: '0',
});

const newVariant = (): Variant => ({
    _key: Math.random().toString(36).slice(2),
    volume_ml: '',
    concentration: '',
    purchase_price_afn: '',
    purchase_currency_id: '',
    sale_price_afn: '',
    sale_currency_id: '',
    wholesale_price_afn: '',
    wholesale_currency_id: '',
    wholesale_min_qty: '10',
    barcodes: [newBarcode(true)],
});

// ✨ CHANGED: default profit currency = ledger currency (not empty)
const newPurchase = (variantIndex: number, defaultCurrencyId?: string): Purchase => ({
    _key: Math.random().toString(36).slice(2),
    variant_index: variantIndex,
    investor_id: 'store',
    supplier_id: 'none',
    quantity: '1',
    profit_per_unit: '0',
    profit_currency_id: defaultCurrencyId || '',
    invoice_number: '',
    purchase_date: today(),
    payment_status: 'paid',
    paid_now: '',
    notes: '',
    billImage: null,
});

const detectBarcodeType = (code: string): string => {
    const clean = code.replace(/\s/g, '');
    if (/^\d{13}$/.test(clean)) return 'EAN13';
    if (/^\d{12}$/.test(clean)) return 'UPC';
    if (/^\d{8}$/.test(clean)) return 'EAN8';
    if (/^[A-Z0-9\-.\ $\/+%]+$/i.test(clean) && clean.length > 13) return 'CODE128';
    if (/^[A-Z0-9\-.\ $\/+%]+$/i.test(clean) && clean.length <= 13) return 'CODE39';
    if (/^[0-9A-Za-z\s\W]+$/.test(clean)) return 'QR';
    return 'other';
};

const toNum = (v: string | number | undefined | null): number => {
    const n = parseFloat(String(v ?? ''));
    return Number.isFinite(n) ? n : 0;
};

const preventNegative = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === '-' || e.key === 'e' || e.key === 'E') e.preventDefault();
};

const isInvestorPurchase = (investorId: string): boolean =>
    investorId !== 'store' && investorId !== 'none' && investorId !== '';

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ProductsCreate({
    brands,
    investors = [],
    suppliers = [],
    currencies = [],
    errors: initialErrors,
}: Props) {
    const { toast } = useToast();
    const { usdToAfn, usdToAed, effectiveAt, source } = useExchange();
    const refUsdToAfn = usdToAfn ?? 0;
    const refUsdToAed = usdToAed ?? 0;
    const ledgerCurrencyId = defaultCurrencyId(currencies);
    const ledgerCur = useMemo(() => findCurrency(currencies, ledgerCurrencyId), [currencies, ledgerCurrencyId]);

    const [form, setForm] = useState({
        name: '',
        brand_id: 'none',
        gender: 'unisex',
        status: 'active' as 'active' | 'inactive',
        description: '',
    });

    const withDefaultCurrency = (variant: Variant): Variant => ({
        ...variant,
        purchase_currency_id: variant.purchase_currency_id || ledgerCurrencyId,
        sale_currency_id: variant.sale_currency_id || ledgerCurrencyId,
        wholesale_currency_id: variant.wholesale_currency_id || ledgerCurrencyId,
    });

    const [variants, setVariants] = useState<Variant[]>([withDefaultCurrency(newVariant())]);
    // ✨ CHANGED: pass ledgerCurrencyId to newPurchase
    const [purchases, setPurchases] = useState<Purchase[]>([newPurchase(0, ledgerCurrencyId)]);
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const barcodeInputRefs = useRef<Record<string, HTMLInputElement | null>>({});
    const barcodeDebounceRefs = useRef<Record<string, ReturnType<typeof setTimeout> | null>>({});

    const allBarcodesInForm = useMemo(
        () => variants.flatMap((v) => (v.barcodes ?? []).filter((b) => b.barcode.trim()).map((b) => b.barcode.trim())),
        [variants],
    );

    useEffect(() => {
        if (initialErrors && Object.keys(initialErrors).length > 0) setErrors(initialErrors);
    }, [initialErrors]);

    /* ═══════════ Variant Handlers ═══════════ */

    const updateVariant = useCallback((index: number, field: keyof Variant, value: any) => {
        setVariants((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };
            return copy;
        });
    }, []);

    // ✨ CHANGED: sync all three price currencies AND all related purchases' profit_currency_id
    const syncVariantCurrency = useCallback((index: number, currencyId: string) => {
        setVariants((prev) => {
            const copy = [...prev];
            copy[index] = {
                ...copy[index],
                purchase_currency_id: currencyId,
                sale_currency_id: currencyId,
                wholesale_currency_id: currencyId,
            };
            return copy;
        });

        // ✨ Sync profit currency of every purchase that belongs to this variant
        setPurchases((prev) =>
            prev.map((p) =>
                p.variant_index === index ? { ...p, profit_currency_id: currencyId } : p,
            ),
        );
    }, []);

    const addVariant = () => {
        const nextIndex = variants.length;
        setVariants((prev) => [...prev, withDefaultCurrency(newVariant())]);
        setPurchases((prev) => [...prev, newPurchase(nextIndex, ledgerCurrencyId)]);
    };

    const removeVariant = (index: number) => {
        if (variants.length === 1) return;
        setVariants((prev) => prev.filter((_, i) => i !== index));
        setPurchases((prev) =>
            prev
                .filter((p) => p.variant_index !== index)
                .map((p) => ({
                    ...p,
                    variant_index: p.variant_index > index ? p.variant_index - 1 : p.variant_index,
                })),
        );
    };

    /* ═══════════ Barcode Handlers ═══════════ */

    const focusBarcodeInput = (vIndex: number, bIndex: number) => {
        const input = barcodeInputRefs.current[`${vIndex}-${bIndex}`];
        if (input) { input.focus(); input.select(); }
    };

    const addBarcode = (vIndex: number, focusAfter = true) => {
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];
        const isFirst = currentBarcodes.length === 0;
        const newBarcodes = [...currentBarcodes, newBarcode(isFirst)];
        updateVariant(vIndex, 'barcodes', newBarcodes);
        if (focusAfter) setTimeout(() => focusBarcodeInput(vIndex, newBarcodes.length - 1), 50);
    };

    const updateBarcode = (vIndex: number, bIndex: number, field: keyof BarcodeItem, value: any) => {
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];
        const barcodes = [...currentBarcodes];
        barcodes[bIndex] = { ...barcodes[bIndex], [field]: value };

        if (field === 'barcode' && typeof value === 'string' && value.length > 5) {
            barcodes[bIndex].barcode_type = detectBarcodeType(value);
        }

        if (field === 'is_primary' && value === true) {
            barcodes.forEach((b, i) => { if (i !== bIndex) barcodes[i] = { ...b, is_primary: false }; });
        }

        if (field === 'barcode') {
            barcodes[bIndex]._exists = undefined;
            barcodes[bIndex]._existingProduct = undefined;
            barcodes[bIndex]._checking = undefined;
        }

        updateVariant(vIndex, 'barcodes', barcodes);

        if (field === 'barcode') {
            const key = `${vIndex}-${bIndex}`;
            if (barcodeDebounceRefs.current[key]) clearTimeout(barcodeDebounceRefs.current[key]!);
            barcodeDebounceRefs.current[key] = setTimeout(() => {
                const code = String(value).trim();
                if (code.length >= 5) void checkBarcodeExists(vIndex, bIndex, code);
            }, 500);
        }
    };

    const removeBarcode = (vIndex: number, bIndex: number) => {
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];
        const removed = currentBarcodes[bIndex];
        const barcodes = currentBarcodes.filter((_, i) => i !== bIndex);
        if (removed?.is_primary && barcodes.length > 0) barcodes[0] = { ...barcodes[0], is_primary: true };
        updateVariant(vIndex, 'barcodes', barcodes);
    };

    const checkBarcodeExists = async (vIndex: number, bIndex: number, code: string): Promise<boolean> => {
        if (!code || code.trim().length < 5) return false;
        const clean = code.trim();
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];

        const duplicatesInForm = allBarcodesInForm.filter((b) => b === clean).length;
        if (duplicatesInForm > 1) {
            const barcodes = [...currentBarcodes];
            barcodes[bIndex] = { ...barcodes[bIndex], _checking: false, _exists: true, _existingProduct: 'Duplicate in this form' };
            updateVariant(vIndex, 'barcodes', barcodes);
            return true;
        }

        const barcodes = [...currentBarcodes];
        barcodes[bIndex] = { ...barcodes[bIndex], _checking: true };
        updateVariant(vIndex, 'barcodes', barcodes);

        try {
            const res = await fetch(`/products/check-barcode?barcode=${encodeURIComponent(clean)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                const data = await res.json();
                const updated = [...currentBarcodes];
                updated[bIndex] = {
                    ...updated[bIndex],
                    _checking: false,
                    _exists: data.exists === true,
                    _existingProduct: data.product_name || null,
                };
                updateVariant(vIndex, 'barcodes', updated);
                if (data.exists) {
                    void getSwal().then((Swal) =>
                        Swal.fire({
                            icon: 'error',
                            title: '❌ Barcode Already Exists',
                            html: data.product_name
                                ? `This barcode is already used by:<br><strong>${data.product_name}</strong>`
                                : 'This barcode is already registered in the system.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc2626',
                        }),
                    );
                }
                return data.exists === true;
            }
            const updated = [...currentBarcodes];
            updated[bIndex] = { ...updated[bIndex], _checking: false, _exists: false };
            updateVariant(vIndex, 'barcodes', updated);
            return false;
        } catch {
            const updated = [...currentBarcodes];
            updated[bIndex] = { ...updated[bIndex], _checking: false, _exists: false };
            updateVariant(vIndex, 'barcodes', updated);
            return false;
        }
    };

    const handleBarcodeEnter = async (vIndex: number, bIndex: number, e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];
        const code = currentBarcodes[bIndex]?.barcode?.trim();
        if (!code) return;
        const key = `${vIndex}-${bIndex}`;
        if (barcodeDebounceRefs.current[key]) {
            clearTimeout(barcodeDebounceRefs.current[key]!);
            barcodeDebounceRefs.current[key] = null;
        }
        await checkBarcodeExists(vIndex, bIndex, code);
    };

    /* ═══════════ Purchase Handlers ═══════════ */

    const updatePurchase = useCallback((index: number, field: keyof Purchase, value: any) => {
        setPurchases((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };
            if (field === 'investor_id' && !isInvestorPurchase(value)) {
                copy[index].profit_per_unit = '0';
            }
            return copy;
        });
    }, []);

    const addPurchase = () => setPurchases((prev) => [...prev, newPurchase(0, ledgerCurrencyId)]);
    const removePurchase = (index: number) => setPurchases((prev) => prev.filter((_, i) => i !== index));

    /* ═══════════ Submit ═══════════ */

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (loading) return;
        setLoading(true);
        setErrors({});

        // 1. Duplicate barcodes within form
        const duplicates = allBarcodesInForm.filter((b, i, arr) => arr.indexOf(b) !== i);
        if (duplicates.length > 0) {
            await (await getSwal()).fire({
                icon: 'error',
                title: 'Duplicate Barcodes in Form',
                html: `These barcodes are duplicated:<br><strong>${[...new Set(duplicates)].join('<br>')}</strong>`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc2626',
            });
            setLoading(false);
            return;
        }

        // 2. Re-check ALL barcodes with DB
        const barcodesToCheck: { vIdx: number; bIdx: number; code: string }[] = [];
        variants.forEach((v, vIdx) => {
            (v.barcodes ?? []).forEach((b, bIdx) => {
                const code = b.barcode.trim();
                if (code.length >= 5) barcodesToCheck.push({ vIdx, bIdx, code });
            });
        });

        if (barcodesToCheck.length > 0) {
            const results = await Promise.all(barcodesToCheck.map((item) => checkBarcodeExists(item.vIdx, item.bIdx, item.code)));
            const hasConflict = results.some((r) => r === true);
            if (hasConflict) {
                const conflicting = barcodesToCheck.filter((_, i) => results[i] === true);
                const listHtml = conflicting
                    .map((item) => {
                        const barcodeItem = variants[item.vIdx].barcodes[item.bIdx];
                        const usedBy = barcodeItem._existingProduct || 'another product';
                        return `<li style="margin-bottom:6px;"><code>${item.code}</code> — used by <strong>${usedBy}</strong></li>`;
                    })
                    .join('');
                await (await getSwal()).fire({
                    icon: 'error',
                    title: '❌ Barcode Already Registered',
                    html: `<p style="margin-bottom:10px;">Cannot save. The following barcodes already exist in the system:</p><ul style="text-align:left;padding-right:20px;">${listHtml}</ul>`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc2626',
                });
                setErrors(
                    conflicting.reduce((acc, item) => {
                        acc[`variants.${item.vIdx}.barcodes.${item.bIdx}.barcode`] = `Barcode ${item.code} is already used`;
                        return acc;
                    }, {} as Record<string, string>),
                );
                setLoading(false);
                setTimeout(() => {
                    document.querySelector('[data-error="true"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
                return;
            }
        }

        // 3. Client-side validation
        const clientErrors: Record<string, string> = {};
        if (!form.name.trim()) clientErrors.name = 'Product name is required';

        variants.forEach((v, i) => {
            if (!v.sale_price_afn || toNum(v.sale_price_afn) < 0 || toNum(v.purchase_price_afn) < 0) {
                clientErrors[`variants.${i}.sale_price_afn`] = 'Prices cannot be negative.';
                return;
            }
            const costAfn = estimateToAfn(toNum(v.purchase_price_afn), findCurrency(currencies, v.purchase_currency_id || ledgerCurrencyId), ledgerCur, refUsdToAfn, refUsdToAed);
            const saleAfn = estimateToAfn(toNum(v.sale_price_afn), findCurrency(currencies, v.sale_currency_id || ledgerCurrencyId), ledgerCur, refUsdToAfn, refUsdToAed);
            if (costAfn !== null && saleAfn !== null && saleAfn < costAfn) {
                clientErrors[`variants.${i}.sale_price_afn`] =
                    'Estimated selling price (AFN) cannot be less than estimated purchase cost (AFN).';
            }
        });

        purchases.forEach((p, i) => {
            if (toNum(p.quantity) <= 0) clientErrors[`purchases.${i}.quantity`] = 'Quantity must be greater than 0';
            if (!p.investor_id || p.investor_id === '' || p.investor_id === 'none') {
                clientErrors[`purchases.${i}.investor_id`] = 'Please select an owner (Shop or an investor)';
            }
            if (isInvestorPurchase(p.investor_id)) {
                const variant = variants[p.variant_index];
                const costAfn = estimateToAfn(toNum(variant?.purchase_price_afn), findCurrency(currencies, variant?.purchase_currency_id || ledgerCurrencyId), ledgerCur, refUsdToAfn, refUsdToAed);
                const saleAfn = estimateToAfn(toNum(variant?.sale_price_afn), findCurrency(currencies, variant?.sale_currency_id || ledgerCurrencyId), ledgerCur, refUsdToAfn, refUsdToAed);
                const investorAfn = estimateToAfn(toNum(p.profit_per_unit), findCurrency(currencies, p.profit_currency_id || ledgerCurrencyId), ledgerCur, refUsdToAfn, refUsdToAed);
                const totalProfit = costAfn !== null && saleAfn !== null ? saleAfn - costAfn : null;
                if (toNum(p.profit_per_unit) <= 0) {
                    clientErrors[`purchases.${i}.profit_per_unit`] = 'Investor profit per unit is required';
                } else if (investorAfn !== null && totalProfit !== null && investorAfn > totalProfit + 0.0001) {
                    clientErrors[`purchases.${i}.profit_per_unit`] =
                        `Investor profit estimate (${fmt(investorAfn)} AFN) exceeds estimated gross profit (${fmt(totalProfit)} AFN).`;
                }
            }
        });

        if (Object.keys(clientErrors).length > 0) {
            setErrors(clientErrors);
            setLoading(false);
            await (await getSwal()).fire({
                icon: 'warning',
                title: 'Validation Failed',
                text: Object.values(clientErrors)[0],
                confirmButtonText: 'OK',
                confirmButtonColor: '#d97706',
            });
            return;
        }

        // 4. Build FormData
        const formData = new FormData();
        formData.append('name', form.name.trim());
        if (form.brand_id && form.brand_id !== 'none') formData.append('brand_id', form.brand_id);
        formData.append('gender', form.gender);
        formData.append('status', form.status);
        if (form.description.trim()) formData.append('description', form.description.trim());

        variants.forEach((v, i) => {
            if (v.volume_ml) formData.append(`variants[${i}][volume_ml]`, v.volume_ml);
            if (v.concentration) formData.append(`variants[${i}][concentration]`, v.concentration);
            formData.append(`variants[${i}][purchase_price_afn]`, v.purchase_price_afn || '0');
            formData.append(`variants[${i}][purchase_amount]`, v.purchase_price_afn || '0');
            formData.append(`variants[${i}][purchase_currency_id]`, v.purchase_currency_id || ledgerCurrencyId);
            formData.append(`variants[${i}][sale_price_afn]`, v.sale_price_afn || '0');
            formData.append(`variants[${i}][sale_amount]`, v.sale_price_afn || '0');
            formData.append(`variants[${i}][sale_currency_id]`, v.sale_currency_id || ledgerCurrencyId);
            formData.append(`variants[${i}][wholesale_price_afn]`, v.wholesale_price_afn || '0');
            formData.append(`variants[${i}][wholesale_amount]`, v.wholesale_price_afn || '0');
            formData.append(`variants[${i}][wholesale_currency_id]`, v.wholesale_currency_id || ledgerCurrencyId);
            formData.append(`variants[${i}][wholesale_min_qty]`, v.wholesale_min_qty || '0');
            (v.barcodes ?? []).forEach((b, j) => {
                if (b.barcode.trim()) {
                    formData.append(`variants[${i}][barcodes][${j}][barcode]`, b.barcode.trim());
                    formData.append(`variants[${i}][barcodes][${j}][barcode_type]`, b.barcode_type || 'EAN13');
                    formData.append(`variants[${i}][barcodes][${j}][is_primary]`, b.is_primary ? '1' : '0');
                    formData.append(`variants[${i}][barcodes][${j}][current_stock]`, b.current_stock || '0');
                }
            });
        });

        purchases.forEach((p, i) => {
            formData.append(`purchases[${i}][variant_index]`, String(p.variant_index));
            if (p.investor_id && p.investor_id !== 'none') formData.append(`purchases[${i}][investor_id]`, p.investor_id);
            if (p.supplier_id && p.supplier_id !== 'none') formData.append(`purchases[${i}][supplier_id]`, p.supplier_id);
            formData.append(`purchases[${i}][quantity]`, p.quantity || '1');
            if (isInvestorPurchase(p.investor_id)) {
                formData.append(`purchases[${i}][profit_per_unit]`, p.profit_per_unit || '0');
                // ✨ always send profit currency (synced already)
                formData.append(`purchases[${i}][profit_currency_id]`, p.profit_currency_id || ledgerCurrencyId);
            }
            if (p.invoice_number) formData.append(`purchases[${i}][invoice_number]`, p.invoice_number);
            formData.append(`purchases[${i}][purchase_date]`, p.purchase_date || today());
            formData.append(`purchases[${i}][payment_status]`, p.payment_status || 'paid');
            if (p.paid_now) formData.append(`purchases[${i}][paid_now]`, p.paid_now);
            if (p.notes) formData.append(`purchases[${i}][notes]`, p.notes);
            if (p.billImage) formData.append(`purchases[${i}][bill_image]`, p.billImage);
        });

        // 5. POST
        router.post('/products', formData, {
            forceFormData: true,
            onSuccess: () => {
                toast({ title: 'Success', description: 'Product created successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);
                const errorCount = Object.keys(err).length;
                const errorListHtml = Object.entries(err)
                    .slice(0, 8)
                    .map(([key, value]) => {
                        const cleanKey = key.replace(/^variants\.\d+\./, 'Variant → ').replace(/^purchases\.\d+\./, 'Purchase → ').replace(/\./g, ' ');
                        return `<li style="margin-bottom:6px;text-align:left;"><strong>${cleanKey}</strong>:<br><span style="color:#b91c1c;">${value}</span></li>`;
                    })
                    .join('');
                void getSwal().then((Swal) =>
                    Swal.fire({
                        icon: 'error',
                        title: `Validation Error (${errorCount})`,
                        html: `<ul style="padding-right:20px;max-height:300px;overflow-y:auto;">${errorListHtml}</ul>`,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#dc2626',
                    }),
                );
                setTimeout(() => {
                    document.querySelector('[data-error="true"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            },
        });
    };

    /* ═══════════ Derived totals ═══════════ */

    const totalBarcodes = allBarcodesInForm.length;
    const totalStock = variants.reduce((sum, v) => sum + (v.barcodes ?? []).reduce((s, b) => s + (parseInt(b.current_stock) || 0), 0), 0);

    /* ═══════════ Render ═══════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Product" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/products" label="Back" />
                    <div className="flex items-center gap-3">
                        <Package className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Product</h1>
                        {totalBarcodes > 0 && (
                            <Badge variant="outline" className="gap-1">
                                <Barcode className="h-3 w-3" /> {totalBarcodes} barcode{totalBarcodes > 1 ? 's' : ''}
                            </Badge>
                        )}
                        {totalStock > 0 && <Badge variant="secondary" className="gap-1">Stock: {totalStock}</Badge>}
                    </div>
                </div>

                {/* Errors Panel */}
                {Object.keys(errors).length > 0 && (
                    <div data-error="true" className="rounded-lg border-2 border-red-400 bg-red-50 p-4">
                        <div className="mb-2 flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-red-600" />
                            <p className="font-bold text-red-800">❌ Validation Errors ({Object.keys(errors).length})</p>
                        </div>
                        <div className="space-y-1 text-xs">
                            {Object.entries(errors).map(([key, value]) => (
                                <div key={key} className="flex gap-2 rounded bg-white/60 px-2 py-1">
                                    <span className="font-mono font-semibold text-red-700">{key}:</span>
                                    <span className="text-red-600">{value}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* STEP 1: Product Information */}
                <Card className="border-l-4 border-l-blue-500">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <span className="bg-primary text-primary-foreground flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold">1</span>
                            Product Information
                        </CardTitle>
                        <CardDescription>Name, brand, gender, status, and description.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="space-y-2">
                            <Label>Product Name <span className="text-destructive">*</span></Label>
                            <Input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                placeholder="e.g. Sauvage"
                                className={errors.name ? 'border-destructive' : ''}
                            />
                            {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Brand</Label>
                                <Select value={form.brand_id} onValueChange={(v) => setForm({ ...form, brand_id: v })}>
                                    <SelectTrigger><SelectValue placeholder="Select brand" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No Brand</SelectItem>
                                        {brands.map((b) => <SelectItem key={b.id} value={String(b.id)}>{b.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Gender <span className="text-destructive">*</span></Label>
                                <Select value={form.gender} onValueChange={(v) => setForm({ ...form, gender: v })}>
                                    <SelectTrigger className={errors.gender ? 'border-destructive' : ''}><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="male">Men</SelectItem>
                                        <SelectItem value="female">Women</SelectItem>
                                        <SelectItem value="unisex">Unisex</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.gender && <p className="text-destructive text-xs">{errors.gender}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v as 'active' | 'inactive' })}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Product Description</Label>
                                <Textarea
                                    value={form.description}
                                    onChange={(e) => setForm({ ...form, description: e.target.value })}
                                    rows={2}
                                    placeholder="Optional general description"
                                    className="min-h-[72px] resize-none"
                                />
                            </div>
                        </div>

                        {(usdToAfn || usdToAed) && (
                            <p className="text-muted-foreground text-xs">
                                Estimates use the central exchange board
                                {usdToAfn ? ` · 1 USD = ${usdToAfn} AFN` : ''}
                                {usdToAed ? ` · 1 USD = ${usdToAed} AED` : ''}
                                {effectiveAt ? ` · effective ${effectiveAt}` : ''}
                                {source ? ` · ${source}` : ''}.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* STEP 2: Variants */}
                <Card className="border-l-4 border-l-emerald-500">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-white">2</span>
                                Variants
                            </CardTitle>
                            <CardDescription>Add one or more variants with their prices.</CardDescription>
                        </div>
                        <Button type="button" onClick={addVariant} className="gap-2">
                            <Plus className="h-4 w-4" /> Add Variant
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {variants.map((v, vIndex) => (
                            <div
                                key={v._key}
                                data-error={errors[`variants.${vIndex}.sale_price_afn`] ? 'true' : undefined}
                                className="flex flex-col gap-4 rounded-lg border p-4"
                            >
                                <div className="mb-1 flex items-center justify-between">
                                    <h3 className="font-semibold">Variant #{vIndex + 1}</h3>
                                    {variants.length > 1 && (
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeVariant(vIndex)} className="text-destructive">
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>

                                {/* BARCODES */}
                                <div className="bg-muted/30 rounded-lg border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Barcode className="h-4 w-4 text-blue-600" />
                                            <Label>Barcodes</Label>
                                            {v.barcodes.length > 0 && <Badge variant="secondary" className="text-xs">{v.barcodes.length}</Badge>}
                                        </div>
                                        <Button type="button" size="sm" variant="outline" onClick={() => addBarcode(vIndex, true)} className="gap-1">
                                            <Plus className="h-3 w-3" /> Add Barcode
                                        </Button>
                                    </div>

                                    {v.barcodes.length === 0 ? (
                                        <div className="flex flex-col items-center gap-2 py-4 text-center">
                                            <Scan className="text-muted-foreground h-8 w-8" />
                                            <p className="text-muted-foreground text-sm">No barcodes yet.</p>
                                            <Button type="button" size="sm" variant="secondary" onClick={() => addBarcode(vIndex, true)} className="gap-1">
                                                <Scan className="h-3 w-3" /> Scan or Type Barcode
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {v.barcodes.map((b, bIndex) => {
                                                const refKey = `${vIndex}-${bIndex}`;
                                                const hasError = b._exists === true;
                                                return (
                                                    <div
                                                        key={bIndex}
                                                        data-error={hasError ? 'true' : undefined}
                                                        className={`flex flex-col gap-2 rounded-md p-2 transition-colors ${hasError ? 'bg-red-50 ring-2 ring-red-300' : ''}`}
                                                    >
                                                        <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                                            <div className="relative min-w-0">
                                                                <Label className="mb-1 block text-xs">Barcode</Label>
                                                                <Input
                                                                    ref={(el) => { barcodeInputRefs.current[refKey] = el; }}
                                                                    placeholder="Scan or type barcode..."
                                                                    value={b.barcode}
                                                                    onChange={(e) => updateBarcode(vIndex, bIndex, 'barcode', e.target.value)}
                                                                    onKeyDown={(e) => handleBarcodeEnter(vIndex, bIndex, e)}
                                                                    onBlur={() => {
                                                                        if (b.barcode.trim().length >= 5) {
                                                                            const key = `${vIndex}-${bIndex}`;
                                                                            if (barcodeDebounceRefs.current[key]) {
                                                                                clearTimeout(barcodeDebounceRefs.current[key]!);
                                                                                barcodeDebounceRefs.current[key] = null;
                                                                            }
                                                                            void checkBarcodeExists(vIndex, bIndex, b.barcode);
                                                                        }
                                                                    }}
                                                                    className={`pr-10 font-mono ${hasError ? 'border-destructive bg-red-50' : ''}`}
                                                                />
                                                                {b._checking && (
                                                                    <div className="absolute top-1/2 right-3 -translate-y-1/2">
                                                                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
                                                                    </div>
                                                                )}
                                                                {!b._checking && b._exists && (
                                                                    <AlertCircle className="text-destructive absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" />
                                                                )}
                                                                {!b._checking && !b._exists && b.barcode.length >= 5 && (
                                                                    <CheckCircle2 className="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-green-600" />
                                                                )}
                                                            </div>
                                                            <div className="min-w-0">
                                                                <Label className="mb-1 block text-xs">Type</Label>
                                                                <Select value={b.barcode_type} onValueChange={(val) => updateBarcode(vIndex, bIndex, 'barcode_type', val)}>
                                                                    <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                                                    <SelectContent>
                                                                        {BARCODE_TYPES.map((t) => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="flex gap-1">
                                                                <Button
                                                                    type="button"
                                                                    variant={b.is_primary ? 'default' : 'ghost'}
                                                                    size="icon"
                                                                    title={b.is_primary ? 'Primary barcode' : 'Set as primary'}
                                                                    onClick={() => updateBarcode(vIndex, bIndex, 'is_primary', true)}
                                                                >
                                                                    <Star className={`h-4 w-4 ${b.is_primary ? 'fill-current' : ''}`} />
                                                                </Button>
                                                                <Button type="button" variant="ghost" size="icon" onClick={() => removeBarcode(vIndex, bIndex)}>
                                                                    <X className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                        {b._exists && (
                                                            <div className="flex items-center gap-1 pl-1 text-xs font-semibold text-red-600">
                                                                <AlertCircle className="h-3 w-3" />⚠ {b._existingProduct || 'This barcode already exists'}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>

                                {/* SIZE */}
                                <div className="space-y-3 rounded-lg border p-4">
                                    <h4 className="text-sm font-semibold">Size</h4>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Volume (ml)</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                onKeyDown={preventNegative}
                                                value={v.volume_ml}
                                                onChange={(e) => { if (toNum(e.target.value) >= 0) updateVariant(vIndex, 'volume_ml', e.target.value); }}
                                                placeholder="100"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Concentration</Label>
                                            <Select value={v.concentration} onValueChange={(val) => updateVariant(vIndex, 'concentration', val)}>
                                                <SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger>
                                                <SelectContent>
                                                    {CONCENTRATIONS.map((c) => <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>)}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>

                                {/* PRODUCT PRICES — ✨ dynamic currency sync */}
                                <div className="space-y-4 rounded-lg border p-4">
                                    <div className="flex items-center gap-2">
                                        <Tag className="h-4 w-4 text-amber-600" />
                                        <h4 className="text-sm font-semibold">Product Prices</h4>
                                    </div>

                                    {/* Purchase cost first */}
                                    <div className="rounded-md border border-blue-200 bg-blue-50/50 p-3">
                                        <MoneyField
                                            label="Purchase cost per unit"
                                            amount={v.purchase_price_afn}
                                            currencyId={v.purchase_currency_id || ledgerCurrencyId}
                                            currencies={currencies}
                                            onAmountChange={(val) => { if (toNum(val) >= 0) updateVariant(vIndex, 'purchase_price_afn', val); }}
                                            // ✨ sync all three price currencies + profit currency
                                            onCurrencyChange={(val) => syncVariantCurrency(vIndex, val)}
                                            error={errors[`variants.${vIndex}.purchase_price_afn`] || errors[`variants.${vIndex}.purchase_currency_id`]}
                                        />
                                    </div>

                                    {/* Sale + Wholesale */}
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <MoneyField
                                            label="Selling price per unit"
                                            required
                                            amount={v.sale_price_afn}
                                            currencyId={v.sale_currency_id || ledgerCurrencyId}
                                            currencies={currencies}
                                            onAmountChange={(val) => { if (toNum(val) >= 0) updateVariant(vIndex, 'sale_price_afn', val); }}
                                            // ✨ sync all three price currencies + profit currency
                                            onCurrencyChange={(val) => syncVariantCurrency(vIndex, val)}
                                            error={errors[`variants.${vIndex}.sale_price_afn`] || errors[`variants.${vIndex}.sale_currency_id`]}
                                        />
                                        <MoneyField
                                            label="Wholesale price per unit"
                                            amount={v.wholesale_price_afn}
                                            currencyId={v.wholesale_currency_id || ledgerCurrencyId}
                                            currencies={currencies}
                                            onAmountChange={(val) => { if (toNum(val) >= 0) updateVariant(vIndex, 'wholesale_price_afn', val); }}
                                            // ✨ sync all three price currencies + profit currency
                                            onCurrencyChange={(val) => syncVariantCurrency(vIndex, val)}
                                            error={errors[`variants.${vIndex}.wholesale_price_afn`] || errors[`variants.${vIndex}.wholesale_currency_id`]}
                                        />
                                    </div>

                                    {/* Wholesale min qty */}
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Wholesale Min Qty</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                onKeyDown={preventNegative}
                                                value={v.wholesale_min_qty}
                                                onChange={(e) => { if (toNum(e.target.value) >= 0) updateVariant(vIndex, 'wholesale_min_qty', e.target.value); }}
                                                placeholder="10"
                                            />
                                        </div>
                                    </div>

                                    {/* Exchange Equivalents */}
                                    <ExchangeEquivalents
                                        variant={v}
                                        currencies={currencies}
                                        ledgerCur={ledgerCur}
                                        refUsdToAfn={refUsdToAfn}
                                        refUsdToAed={refUsdToAed}
                                    />
                                </div>

                                {/* OWNER & INVESTOR PROFIT */}
                                <div className="bg-muted/30 rounded-lg border p-4">
                                    <div className="mb-3 flex items-center gap-1 text-sm font-semibold">
                                        <User className="h-4 w-4" /> Owner & Investor Profit
                                    </div>
                                    {purchases.filter((p) => p.variant_index === vIndex).map((p) => {
                                        const pIndex = purchases.findIndex((row) => row._key === p._key);
                                        const investorBuy = isInvestorPurchase(p.investor_id);
                                        return (
                                            <div key={p._key} className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label className="flex items-center gap-1">
                                                        <User className="h-3 w-3" /> Owner <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Select value={p.investor_id || 'store'} onValueChange={(val) => updatePurchase(pIndex, 'investor_id', val)}>
                                                        <SelectTrigger className={errors[`purchases.${pIndex}.investor_id`] ? 'border-destructive' : ''}>
                                                            <SelectValue placeholder="Select owner" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="store">🏪 Shop (Store-owned)</SelectItem>
                                                            {investors.map((inv) => <SelectItem key={inv.id} value={String(inv.id)}>👤 {inv.name}</SelectItem>)}
                                                        </SelectContent>
                                                    </Select>
                                                    {errors[`purchases.${pIndex}.investor_id`] && (
                                                        <p className="text-destructive text-xs">{errors[`purchases.${pIndex}.investor_id`]}</p>
                                                    )}
                                                </div>
                                                <div className="space-y-2">
                                                    <MoneyField
                                                        label="Investor profit / unit"
                                                        required={investorBuy}
                                                        amount={p.profit_per_unit}
                                                        // ✨ profit currency is kept in sync via syncVariantCurrency
                                                        currencyId={p.profit_currency_id || ledgerCurrencyId}
                                                        currencies={currencies}
                                                        disabled={!investorBuy}
                                                        onAmountChange={(val) => { if (toNum(val) >= 0) updatePurchase(pIndex, 'profit_per_unit', val); }}
                                                        onCurrencyChange={(val) => updatePurchase(pIndex, 'profit_currency_id', val)}
                                                        error={errors[`purchases.${pIndex}.profit_per_unit`] || errors[`purchases.${pIndex}.profit_currency_id`]}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {/* STEP 3: Purchases */}
                <Card className="border-l-4 border-l-purple-500">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-purple-500 text-xs font-bold text-white">3</span>
                                Purchases
                            </CardTitle>
                            <CardDescription>Record purchases with details, invoice, and bill image.</CardDescription>
                        </div>
                        <Button type="button" onClick={addPurchase} className="gap-2">
                            <Plus className="h-4 w-4" /> Add Purchase
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {purchases.length === 0 && (
                            <div className="text-muted-foreground py-6 text-center text-sm">
                                No purchases yet. Click <strong>Add Purchase</strong> to record one.
                            </div>
                        )}

                        {purchases.map((p, pIndex) => {
                            const isInvestor = isInvestorPurchase(p.investor_id);
                            const variant = variants[p.variant_index];
                            const defaultCur = findCurrency(currencies, ledgerCurrencyId);
                            const purchaseCur = findCurrency(currencies, variant?.purchase_currency_id || ledgerCurrencyId);
                            const saleCur = findCurrency(currencies, variant?.sale_currency_id || ledgerCurrencyId);
                            const profitCur = findCurrency(currencies, p.profit_currency_id || ledgerCurrencyId);
                            const costLedger = estimateToAfn(toNum(variant?.purchase_price_afn), purchaseCur, defaultCur, refUsdToAfn, refUsdToAed);
                            const saleLedger = estimateToAfn(toNum(variant?.sale_price_afn), saleCur, defaultCur, refUsdToAfn, refUsdToAed);
                            const investorLedger = estimateToAfn(isInvestor ? toNum(p.profit_per_unit) : 0, profitCur, defaultCur, refUsdToAfn, refUsdToAed);
                            const unitPrice = costLedger ?? toNum(variant?.purchase_price_afn);
                            const profits = calcProfits(costLedger ?? 0, saleLedger ?? 0, investorLedger ?? 0, toNum(p.quantity));

                            return (
                                <div key={p._key} data-error={errors[`purchases.${pIndex}.quantity`] ? 'true' : undefined} className="rounded-lg border p-4">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h3 className="flex items-center gap-2 font-semibold"><Receipt className="h-4 w-4" /> Purchase #{pIndex + 1}</h3>
                                        {purchases.length > 1 && (
                                            <Button type="button" variant="ghost" size="icon" onClick={() => removePurchase(pIndex)} className="text-destructive">
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>

                                    <div className="bg-muted/30 mb-4 rounded-lg border p-4">
                                        <h4 className="mb-3 text-sm font-semibold">Variant & Quantity</h4>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Which variant is this purchase for?</Label>
                                                <Select
                                                    value={String(p.variant_index)}
                                                    onValueChange={(val) => updatePurchase(pIndex, 'variant_index', Number(val))}
                                                >
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {variants.map((vv, vvIdx) => (
                                                            <SelectItem key={vv._key} value={String(vvIdx)}>
                                                                Variant #{vvIdx + 1} {vv.volume_ml ? `— ${vv.volume_ml}ml` : ''} {vv.concentration ? `(${vv.concentration})` : ''}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Quantity</Label>
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    onKeyDown={preventNegative}
                                                    value={p.quantity}
                                                    onChange={(e) => { if (toNum(e.target.value) >= 0) updatePurchase(pIndex, 'quantity', e.target.value); }}
                                                    className={errors[`purchases.${pIndex}.quantity`] ? 'border-destructive' : ''}
                                                />
                                                {errors[`purchases.${pIndex}.quantity`] && (
                                                    <p className="text-destructive text-xs">{errors[`purchases.${pIndex}.quantity`]}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mb-4 rounded-lg border p-4">
                                        <h4 className="mb-3 flex items-center gap-1 text-sm font-semibold"><TrendingUp className="h-3 w-3" /> Totals & Profit Split (estimate)</h4>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <div className="space-y-2">
                                                <Label className="text-xs">Purchase line (AFN est.)</Label>
                                                <div className="bg-muted/30 flex h-10 items-center rounded-md border border-dashed px-3 font-mono text-sm">
                                                    {fmt(unitPrice * toNum(p.quantity))} <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-xs text-blue-700">Investor profit (est.)</Label>
                                                <div className="flex h-10 items-center rounded-md border border-dashed bg-blue-50 px-3 font-mono text-sm font-semibold text-blue-700">
                                                    {fmt(toNum(p.profit_per_unit))} {profitCur?.code || ''}
                                                    <span className="ml-2 text-xs font-normal">≈ {fmt(profits.investorLine)} AFN</span>
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-xs text-emerald-700">Store profit (AFN est.)</Label>
                                                <div className="flex h-10 items-center rounded-md border border-dashed bg-emerald-50 px-3 font-mono text-sm font-semibold text-emerald-700">
                                                    {fmt(profits.storeLine)} <span className="ml-1 text-xs">AFN</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mb-4 rounded-lg border p-4">
                                        <h4 className="mb-3 text-sm font-semibold">Bill & Payment</h4>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Invoice Number</Label>
                                                <Input value={p.invoice_number} onChange={(e) => updatePurchase(pIndex, 'invoice_number', e.target.value)} placeholder="e.g. INV-2024-001" />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Purchase Date</Label>
                                                <Input type="date" value={p.purchase_date} onChange={(e) => updatePurchase(pIndex, 'purchase_date', e.target.value)} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Supplier</Label>
                                                <Select value={p.supplier_id} onValueChange={(val) => updatePurchase(pIndex, 'supplier_id', val)}>
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">No Supplier</SelectItem>
                                                        {suppliers.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Payment Status</Label>
                                                <Select value={p.payment_status} onValueChange={(val) => updatePurchase(pIndex, 'payment_status', val)}>
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {PAYMENT_STATUSES.map((s) => <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>)}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            {p.payment_status === 'partial' && (
                                                <div className="space-y-2">
                                                    <Label>Paid Now</Label>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        onKeyDown={preventNegative}
                                                        value={p.paid_now}
                                                        onChange={(e) => { if (toNum(e.target.value) >= 0) updatePurchase(pIndex, 'paid_now', e.target.value); }}
                                                        placeholder="Amount paid now"
                                                    />
                                                </div>
                                            )}
                                            <div className="space-y-2">
                                                <Label>Bill Image</Label>
                                                <Input type="file" accept="image/*" onChange={(e) => updatePurchase(pIndex, 'billImage', e.target.files?.[0] || null)} />
                                            </div>
                                            <div className="space-y-2 md:col-span-2">
                                                <Label>Notes</Label>
                                                <Textarea value={p.notes} onChange={(e) => updatePurchase(pIndex, 'notes', e.target.value)} rows={2} placeholder="Optional notes about this purchase" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={loading} className="gap-2">
                        <Save className="h-4 w-4" /> {loading ? 'Saving...' : 'Save Product'}
                    </Button>
                    <BackButton href="/products" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}

/* ═══════════════════════════════════════════════════════
 |  Exchange Equivalents sub-component
 ═══════════════════════════════════════════════════════ */

function ExchangeEquivalents({
    variant,
    currencies,
    ledgerCur,
    refUsdToAfn,
    refUsdToAed,
}: {
    variant: Variant;
    currencies: CatalogCurrency[];
    ledgerCur: CatalogCurrency | null;
    refUsdToAfn: number;
    refUsdToAed: number;
}) {
    const purchaseCur = findCurrency(currencies, variant.purchase_currency_id);
    const saleCur = findCurrency(currencies, variant.sale_currency_id);

    const purchaseAfn = estimateToAfn(toNum(variant.purchase_price_afn), purchaseCur, ledgerCur, refUsdToAfn, refUsdToAed);
    const saleAfn = estimateToAfn(toNum(variant.sale_price_afn), saleCur, ledgerCur, refUsdToAfn, refUsdToAed);

    if (purchaseAfn === null && saleAfn === null) {
        return (
            <div className="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                Enter purchase and sale prices above to see exchange equivalents.
            </div>
        );
    }

    return (
        <div className="rounded-md border border-dashed bg-gray-50/60 p-3">
            <div className="mb-2 flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                <ArrowRightLeft className="h-3.5 w-3.5" />
                Exchange Equivalents (estimate)
            </div>
            <div className="grid grid-cols-1 gap-2 text-xs sm:grid-cols-3">
                <div className="rounded bg-white p-2">
                    <div className="text-muted-foreground mb-0.5 text-[10px] uppercase tracking-wide">Purchase → AFN</div>
                    <div className="font-mono font-semibold">{purchaseAfn !== null ? fmt(purchaseAfn) : '—'} <span className="text-muted-foreground">AFN</span></div>
                </div>
                <div className="rounded bg-white p-2">
                    <div className="text-muted-foreground mb-0.5 text-[10px] uppercase tracking-wide">Sale → AFN</div>
                    <div className="font-mono font-semibold">{saleAfn !== null ? fmt(saleAfn) : '—'} <span className="text-muted-foreground">AFN</span></div>
                </div>
                <div className="rounded bg-white p-2">
                    <div className="text-muted-foreground mb-0.5 text-[10px] uppercase tracking-wide">Gross margin</div>
                    <div className="font-mono font-semibold text-emerald-700">
                        {purchaseAfn !== null && saleAfn !== null ? fmt(saleAfn - purchaseAfn) : '—'}
                        <span className="text-muted-foreground ml-1">AFN</span>
                    </div>
                </div>
            </div>
        </div>
    );
}