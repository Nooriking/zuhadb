// resources/js/pages/products/edit.tsx

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
    ArrowLeft,
    Barcode,
    CheckCircle2,
    Package,
    Plus,
    Receipt,
    Save,
    Scan,
    Star,
    Trash2,
    TrendingUp,
    User,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Swal from 'sweetalert2';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Brand {
    id: number;
    name: string;
}

interface Investor {
    id: number;
    name: string;
}

interface Supplier {
    id: number;
    name: string;
}

interface BarcodeItem {
    id?: number;
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
    id?: number;
    volume_ml: string;
    concentration: string;

    // Prices
    purchase_price_afn: string;
    sale_price_afn: string;
    wholesale_price_afn: string;
    wholesale_min_qty: string;
    minimum_stock: string;

    barcodes: BarcodeItem[];
}

interface Purchase {
    _key: string;
    id?: number;
    variant_index: number;
    investor_id: string;
    supplier_id: string;
    quantity: string;
    profit_per_unit: string;
    line_total: string;
    line_profit: string;
    invoice_number: string;
    purchase_date: string;
    payment_status: string;
    paid_now: string;
    exchange_rate: string;
    notes: string;
    billImage?: File | null;
    bill_image_url?: string | null;
}

interface ProductData {
    id: number;
    name: string | null;
    brand_id: number | null;
    gender: 'male' | 'female' | 'unisex';
    status: 'active' | 'inactive';
    description: string | null;
    variants: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        purchase_price_afn: string;
        sale_price_afn: string;
        wholesale_price_afn: string;
        wholesale_min_qty: number;
        minimum_stock: number;
        barcodes: {
            id: number;
            barcode: string;
            barcode_type: string;
            is_primary: boolean;
            current_stock: number;
        }[];
    }[];
}

interface PurchaseData {
    id: number;
    variant_id: number;
    investor_id: number | null;
    supplier_id: number | null;
    quantity: number;
    remaining_quantity: number;
    invoice_number: string | null;
    purchase_date: string;
    payment_status: string | null;
    exchange_rate: string | null;
    profit_per_unit: string;
    line_total: string;
    line_profit: string;
    notes: string | null;
    bill_image: string | null;
}

interface Props {
    product: ProductData;
    purchases?: PurchaseData[];
    brands: Brand[];
    investors?: Investor[];
    suppliers?: Supplier[];
    errors?: Record<string, string>;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

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
    sale_price_afn: '',
    wholesale_price_afn: '',
    wholesale_min_qty: '10',
    minimum_stock: '0',
    barcodes: [newBarcode(true)],
});

const newPurchase = (variantIndex: number): Purchase => ({
    _key: Math.random().toString(36).slice(2),
    variant_index: variantIndex,
    investor_id: 'store',
    supplier_id: 'none',
    quantity: '1',
    profit_per_unit: '0',
    line_total: '0',
    line_profit: '0',
    invoice_number: '',
    purchase_date: today(),
    payment_status: 'paid',
    paid_now: '',
    exchange_rate: '',
    notes: '',
    billImage: null,
});

const toVariant = (v: any): Variant => ({
    _key: Math.random().toString(36).slice(2),
    id: v.id,
    volume_ml: v.volume_ml ? String(v.volume_ml) : '',
    concentration: v.concentration || '',
    purchase_price_afn: v.purchase_price_afn || '',
    sale_price_afn: v.sale_price_afn || '',
    wholesale_price_afn: v.wholesale_price_afn || '',
    wholesale_min_qty: v.wholesale_min_qty ? String(v.wholesale_min_qty) : '10',
    minimum_stock: v.minimum_stock ? String(v.minimum_stock) : '0',
    barcodes: (v.barcodes || []).map((b: any) => ({
        id: b.id,
        barcode: b.barcode,
        barcode_type: b.barcode_type || 'EAN13',
        is_primary: b.is_primary,
        current_stock: String(b.current_stock ?? 0),
    })),
});

const toPurchase = (p: any, variantIndex: number): Purchase => ({
    _key: Math.random().toString(36).slice(2),
    id: p.id,
    variant_index: variantIndex,
    investor_id: p.investor_id ? String(p.investor_id) : 'store',
    supplier_id: p.supplier_id ? String(p.supplier_id) : 'none',
    quantity: String(p.quantity || 1),
    profit_per_unit: String(p.profit_per_unit || 0),
    line_total: String(p.line_total || 0),
    line_profit: String(p.line_profit || 0),
    invoice_number: p.invoice_number || '',
    purchase_date: p.purchase_date || today(),
    payment_status: p.payment_status || 'paid',
    paid_now: '',
    exchange_rate: p.exchange_rate ? String(p.exchange_rate) : '',
    notes: p.notes || '',
    billImage: null,
    bill_image_url: p.bill_image || null,
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
    if (e.key === '-' || e.key === 'e' || e.key === 'E') {
        e.preventDefault();
    }
};

const isInvestorPurchase = (investorId: string): boolean => {
    return investorId !== 'store' && investorId !== 'none' && investorId !== '';
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ProductsEdit({
    product,
    purchases: initialPurchases = [],
    brands,
    investors = [],
    suppliers = [],
    errors: initialErrors,
}: Props) {
    const { toast } = useToast();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
        { title: `Product #${product.id}`, href: `/products/${product.id}` },
        { title: 'Edit', href: `/products/${product.id}/edit` },
    ];

    /* ─── Form state ─── */
    const [form, setForm] = useState({
        name: product.name || '',
        brand_id: product.brand_id ? String(product.brand_id) : 'none',
        gender: product.gender || 'unisex',
        status: product.status || 'active',
        description: product.description || '',
    });

    /* ─── Variants ─── */
    const [variants, setVariants] = useState<Variant[]>(product.variants.length > 0 ? product.variants.map(toVariant) : [newVariant()]);

    /* ─── Purchases ─── */
    const [purchases, setPurchases] = useState<Purchase[]>(() => {
        if (initialPurchases.length === 0) {
            return [newPurchase(0)];
        }

        // Map variant_id → index
        return initialPurchases.map((p) => {
            const idx = product.variants.findIndex((v) => v.id === p.variant_id);
            return toPurchase(p, idx >= 0 ? idx : 0);
        });
    });

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const barcodeInputRefs = useRef<Record<string, HTMLInputElement | null>>({});
    const barcodeDebounceRefs = useRef<Record<string, ReturnType<typeof setTimeout> | null>>({});

    const allBarcodesInForm = useMemo(
        () => variants.flatMap((v) => (v.barcodes ?? []).filter((b) => b.barcode.trim()).map((b) => b.barcode.trim())),
        [variants],
    );

    useEffect(() => {
        if (initialErrors && Object.keys(initialErrors).length > 0) {
            setErrors(initialErrors);
        }
    }, [initialErrors]);

    /* ═══════════ Variant Handlers ═══════════ */

    const updateVariant = (index: number, field: keyof Variant, value: any) => {
        setVariants((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };
            return copy;
        });

        // ✅ اگر purchase_price_afn تغییر کرد، purchases مرتبط را recalc کن
        if (field === 'purchase_price_afn') {
            setPurchases((prev) =>
                prev.map((p) => {
                    if (p.variant_index !== index) return p;
                    return p; // recalc در render اتفاق می‌افتد
                }),
            );
        }
    };

    const addVariant = () => {
        const nextIndex = variants.length;
        setVariants((prev) => [...prev, newVariant()]);
        setPurchases((prev) => [...prev, newPurchase(nextIndex)]);
    };

    const removeVariant = (index: number) => {
        if (variants.length === 1) return;

        Swal.fire({
            icon: 'warning',
            title: 'Remove this variant?',
            text: 'It will be moved to trash after you save.',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
        }).then((result) => {
            if (result.isConfirmed) {
                setVariants((prev) => prev.filter((_, i) => i !== index));
                setPurchases((prev) =>
                    prev
                        .filter((p) => p.variant_index !== index)
                        .map((p) => ({
                            ...p,
                            variant_index: p.variant_index > index ? p.variant_index - 1 : p.variant_index,
                        })),
                );
            }
        });
    };

    /* ═══════════ Barcode Handlers ═══════════ */

    const focusBarcodeInput = (vIndex: number, bIndex: number) => {
        const input = barcodeInputRefs.current[`${vIndex}-${bIndex}`];
        if (input) {
            input.focus();
            input.select();
        }
    };

    const addBarcode = (vIndex: number, focusAfter = true) => {
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];
        const isFirst = currentBarcodes.length === 0;
        const newBarcodes = [...currentBarcodes, newBarcode(isFirst)];
        updateVariant(vIndex, 'barcodes', newBarcodes);
        if (focusAfter) {
            setTimeout(() => focusBarcodeInput(vIndex, newBarcodes.length - 1), 50);
        }
    };

    const updateBarcode = (vIndex: number, bIndex: number, field: keyof BarcodeItem, value: any) => {
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];
        const barcodes = [...currentBarcodes];
        barcodes[bIndex] = { ...barcodes[bIndex], [field]: value };

        if (field === 'barcode' && typeof value === 'string' && value.length > 5) {
            barcodes[bIndex].barcode_type = detectBarcodeType(value);
        }

        if (field === 'is_primary' && value === true) {
            barcodes.forEach((b, i) => {
                if (i !== bIndex) barcodes[i] = { ...b, is_primary: false };
            });
        }

        if (field === 'barcode') {
            barcodes[bIndex]._exists = undefined;
            barcodes[bIndex]._existingProduct = undefined;
            barcodes[bIndex]._checking = undefined;
        }

        updateVariant(vIndex, 'barcodes', barcodes);

        if (field === 'barcode') {
            const key = `${vIndex}-${bIndex}`;
            if (barcodeDebounceRefs.current[key]) {
                clearTimeout(barcodeDebounceRefs.current[key]!);
            }
            barcodeDebounceRefs.current[key] = setTimeout(() => {
                const code = String(value).trim();
                if (code.length >= 5) {
                    checkBarcodeExists(vIndex, bIndex, code);
                }
            }, 500);
        }
    };

    const removeBarcode = (vIndex: number, bIndex: number) => {
        const currentBarcodes = variants[vIndex]?.barcodes ?? [];
        const removed = currentBarcodes[bIndex];
        const barcodes = currentBarcodes.filter((_, i) => i !== bIndex);

        if (removed?.is_primary && barcodes.length > 0) {
            barcodes[0] = { ...barcodes[0], is_primary: true };
        }

        updateVariant(vIndex, 'barcodes', barcodes);
    };

    const checkBarcodeExists = async (vIndex: number, bIndex: number, code: string): Promise<boolean> => {
        if (!code || code.trim().length < 5) return false;
        const clean = code.trim();

        const currentBarcodes = variants[vIndex]?.barcodes ?? [];

        // Duplicate in form?
        const duplicatesInForm = allBarcodesInForm.filter((b) => b === clean).length;
        if (duplicatesInForm > 1) {
            const barcodes = [...currentBarcodes];
            barcodes[bIndex] = {
                ...barcodes[bIndex],
                _checking: false,
                _exists: true,
                _existingProduct: 'Duplicate in this form',
            };
            updateVariant(vIndex, 'barcodes', barcodes);
            return true;
        }

        // Loading
        const barcodes = [...currentBarcodes];
        barcodes[bIndex] = { ...barcodes[bIndex], _checking: true };
        updateVariant(vIndex, 'barcodes', barcodes);

        try {
            const currentVariantId = variants[vIndex].id;
            const params = new URLSearchParams({ barcode: clean });
            if (currentVariantId) params.append('ignore_variant_id', String(currentVariantId));

            const res = await fetch(`/products/check-barcode?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
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
                    Swal.fire({
                        icon: 'error',
                        title: '❌ Barcode Already Exists',
                        html: data.product_name
                            ? `This barcode is already used by:<br><strong>${data.product_name}</strong>`
                            : 'This barcode is already registered in the system.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#dc2626',
                    });
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

    const updatePurchase = (index: number, field: keyof Purchase, value: any) => {
        setPurchases((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };

            if (field === 'investor_id' && !isInvestorPurchase(value)) {
                copy[index].profit_per_unit = '0';
                copy[index].line_profit = '0';
            }

            return copy;
        });

        // ✅ quantity یا investor_id → recalc
        if (field === 'quantity' || field === 'investor_id') {
            recalcPurchase(index);
        }
    };

    const recalcPurchase = (index: number) => {
        setPurchases((prev) => {
            const copy = [...prev];
            const p = copy[index];
            const variant = variants[p.variant_index];

            const qty = toNum(p.quantity);
            const unitPrice = toNum(variant?.purchase_price_afn);
            const profit = toNum(p.profit_per_unit);

            p.line_total = (unitPrice * qty).toFixed(2);

            if (isInvestorPurchase(p.investor_id)) {
                p.line_profit = (qty * profit).toFixed(2);
            } else {
                p.line_profit = '0';
            }

            return copy;
        });
    };

    const addPurchase = () => {
        setPurchases((prev) => [...prev, newPurchase(0)]);
    };

    const removePurchase = (index: number) => {
        setPurchases((prev) => prev.filter((_, i) => i !== index));
    };

    /* ═══════════ Submit ═══════════ */

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (loading) return;
        setLoading(true);
        setErrors({});

        /* ─── 1. Duplicate barcodes within form ─── */
        const duplicates = allBarcodesInForm.filter((b, i, arr) => arr.indexOf(b) !== i);
        if (duplicates.length > 0) {
            await Swal.fire({
                icon: 'error',
                title: 'Duplicate Barcodes in Form',
                html: `These barcodes are duplicated:<br><strong>${[...new Set(duplicates)].join('<br>')}</strong>`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc2626',
            });
            setLoading(false);
            return;
        }

        /* ─── 2. Re-check ALL barcodes with DB ─── */
        const barcodesToCheck: { vIdx: number; bIdx: number; code: string }[] = [];

        variants.forEach((v, vIdx) => {
            (v.barcodes ?? []).forEach((b, bIdx) => {
                const code = b.barcode.trim();
                if (code.length >= 5) {
                    barcodesToCheck.push({ vIdx, bIdx, code });
                }
            });
        });

        if (barcodesToCheck.length > 0) {
            const results = await Promise.all(barcodesToCheck.map((item) => checkBarcodeExists(item.vIdx, item.bIdx, item.code)));

            const hasConflict = results.some((r) => r === true);
            if (hasConflict) {
                const conflicting = barcodesToCheck.filter((_, i) => results[i] === true);

                const listHtml = conflicting
                    .map((item) => {
                        const variant = variants[item.vIdx];
                        const barcodeItem = variant.barcodes[item.bIdx];
                        const usedBy = barcodeItem._existingProduct || 'another product';
                        return `<li style="margin-bottom:6px;"><code>${item.code}</code> — used by <strong>${usedBy}</strong></li>`;
                    })
                    .join('');

                await Swal.fire({
                    icon: 'error',
                    title: '❌ Barcode Already Registered',
                    html: `<p style="margin-bottom:10px;">Cannot save. The following barcodes already exist in the system:</p>
                           <ul style="text-align:left;padding-right:20px;">${listHtml}</ul>`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc2626',
                });

                setErrors(
                    conflicting.reduce(
                        (acc, item) => {
                            acc[`variants.${item.vIdx}.barcodes.${item.bIdx}.barcode`] = `Barcode ${item.code} is already used`;
                            return acc;
                        },
                        {} as Record<string, string>,
                    ),
                );

                setLoading(false);
                setTimeout(() => {
                    const errorEl = document.querySelector('[data-error="true"]');
                    errorEl?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);

                return;
            }
        }

        /* ─── 3. Client-side validation ─── */
        const clientErrors: Record<string, string> = {};

        if (!form.name.trim()) {
            clientErrors.name = 'Product name is required';
        }

        variants.forEach((v, i) => {
            if (!v.sale_price_afn || toNum(v.sale_price_afn) < 0 || toNum(v.purchase_price_afn) < 0) {
                clientErrors[`variants.${i}.sale_price_afn`] = 'Prices cannot be negative.';
            } else if (toNum(v.sale_price_afn) < toNum(v.purchase_price_afn)) {
                clientErrors[`variants.${i}.sale_price_afn`] = 'Sale price cannot be less than the purchase price.';
            }
        });

        purchases.forEach((p, i) => {
            if (toNum(p.quantity) <= 0) {
                clientErrors[`purchases.${i}.quantity`] = 'Quantity must be greater than 0';
            }
            if (isInvestorPurchase(p.investor_id) && toNum(p.profit_per_unit) <= 0) {
                clientErrors[`purchases.${i}.profit_per_unit`] = 'Profit per unit is required for investor purchases';
            }
        });

        if (Object.keys(clientErrors).length > 0) {
            setErrors(clientErrors);
            setLoading(false);
            await Swal.fire({
                icon: 'warning',
                title: 'Validation Failed',
                text: Object.values(clientErrors)[0],
                confirmButtonText: 'OK',
                confirmButtonColor: '#d97706',
            });
            return;
        }

        /* ─── 4. Build FormData ─── */
        const formData = new FormData();
        formData.append('_method', 'PUT');

        formData.append('name', form.name.trim());
        if (form.brand_id && form.brand_id !== 'none') {
            formData.append('brand_id', form.brand_id);
        }
        formData.append('gender', form.gender);
        formData.append('status', form.status);
        if (form.description.trim()) {
            formData.append('description', form.description.trim());
        }

        // Variants
        variants.forEach((v, i) => {
            if (v.id) formData.append(`variants[${i}][id]`, String(v.id));

            if (v.volume_ml) formData.append(`variants[${i}][volume_ml]`, v.volume_ml);
            if (v.concentration) {
                formData.append(`variants[${i}][concentration]`, v.concentration);
            }

            formData.append(`variants[${i}][purchase_price_afn]`, v.purchase_price_afn || '0');
            formData.append(`variants[${i}][sale_price_afn]`, v.sale_price_afn || '0');
            formData.append(`variants[${i}][wholesale_price_afn]`, v.wholesale_price_afn || '0');
            formData.append(`variants[${i}][wholesale_min_qty]`, v.wholesale_min_qty || '0');
            formData.append(`variants[${i}][minimum_stock]`, v.minimum_stock || '0');

            (v.barcodes ?? []).forEach((b, j) => {
                if (b.barcode.trim()) {
                    formData.append(`variants[${i}][barcodes][${j}][barcode]`, b.barcode.trim());
                    formData.append(`variants[${i}][barcodes][${j}][barcode_type]`, b.barcode_type || 'EAN13');
                    formData.append(`variants[${i}][barcodes][${j}][is_primary]`, b.is_primary ? '1' : '0');
                    formData.append(`variants[${i}][barcodes][${j}][current_stock]`, b.current_stock || '0');
                }
            });
        });

        // Purchases
        purchases.forEach((p, i) => {
            if (p.id) formData.append(`purchases[${i}][id]`, String(p.id));

            formData.append(`purchases[${i}][variant_index]`, String(p.variant_index));

            if (p.investor_id && p.investor_id !== 'store' && p.investor_id !== 'none') {
                formData.append(`purchases[${i}][investor_id]`, p.investor_id);
            }
            if (p.supplier_id && p.supplier_id !== 'none') {
                formData.append(`purchases[${i}][supplier_id]`, p.supplier_id);
            }

            formData.append(`purchases[${i}][quantity]`, p.quantity || '1');

            if (isInvestorPurchase(p.investor_id)) {
                formData.append(`purchases[${i}][profit_per_unit]`, p.profit_per_unit || '0');
            }

            if (p.invoice_number) {
                formData.append(`purchases[${i}][invoice_number]`, p.invoice_number);
            }
            formData.append(`purchases[${i}][purchase_date]`, p.purchase_date || today());
            if (p.exchange_rate) formData.append(`purchases[${i}][exchange_rate]`, p.exchange_rate);
            if (p.paid_now) formData.append(`purchases[${i}][paid_now]`, p.paid_now);
            if (p.notes) {
                formData.append(`purchases[${i}][notes]`, p.notes);
            }
            if (p.billImage) {
                formData.append(`purchases[${i}][bill_image]`, p.billImage);
            }
        });

        /* ─── 5. POST ─── */
        router.post(`/products/${product.id}`, formData, {
            forceFormData: true,
            onSuccess: () => {
                toast({ title: 'Success', description: 'Product updated successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);

                const errorCount = Object.keys(err).length;
                const errorListHtml = Object.entries(err)
                    .slice(0, 8)
                    .map(([key, value]) => {
                        const cleanKey = key
                            .replace(/^variants\.\d+\./, 'Variant → ')
                            .replace(/^purchases\.\d+\./, 'Purchase → ')
                            .replace(/\./g, ' ');
                        return `<li style="margin-bottom:6px;text-align:left;"><strong>${cleanKey}</strong>:<br><span style="color:#b91c1c;">${value}</span></li>`;
                    })
                    .join('');

                Swal.fire({
                    icon: 'error',
                    title: `Validation Error (${errorCount})`,
                    html: `<ul style="padding-right:20px;max-height:300px;overflow-y:auto;">${errorListHtml}</ul>`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc2626',
                });
            },
        });
    };

    /* ═══════════ Derived ═══════════ */

    const totalBarcodes = allBarcodesInForm.length;

    /* ═══════════ Render ═══════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Product #${product.id}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Package className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Product</h1>
                        <Badge variant="outline">#{product.id}</Badge>
                        {totalBarcodes > 0 && (
                            <Badge variant="outline" className="gap-1">
                                <Barcode className="h-3 w-3" /> {totalBarcodes} barcode
                                {totalBarcodes > 1 ? 's' : ''}
                            </Badge>
                        )}
                    </div>
                    <Link href="/products">
                        <Button variant="outline" type="button" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
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

                {/* ═══════════ STEP 1: Product Information ═══════════ */}
                <Card className="border-l-4 border-l-blue-500">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <span className="bg-primary text-primary-foreground flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold">
                                1
                            </span>
                            Product Information
                        </CardTitle>
                        <CardDescription>Basic product info — name, brand, gender, status, and description.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="space-y-2">
                            <Label>
                                Product Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                placeholder="e.g. Sauvage"
                                className={errors.name ? 'border-destructive' : ''}
                            />
                            {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label>Brand</Label>
                                <Select value={form.brand_id} onValueChange={(v) => setForm({ ...form, brand_id: v })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select brand" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No Brand</SelectItem>
                                        {brands.map((b) => (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>
                                    Gender <span className="text-destructive">*</span>
                                </Label>
                                <Select value={form.gender} onValueChange={(v) => setForm({ ...form, gender: v as any })}>
                                    <SelectTrigger className={errors.gender ? 'border-destructive' : ''}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="male">Men</SelectItem>
                                        <SelectItem value="female">Women</SelectItem>
                                        <SelectItem value="unisex">Unisex</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.gender && <p className="text-destructive text-xs">{errors.gender}</p>}
                            </div>

                        </div>

                        <div className="space-y-2">
                            <Label>Product Description</Label>
                            <Textarea
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                                rows={2}
                                placeholder="Optional general description for the product"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ STEP 2: Variants ═══════════ */}
                <Card className="border-l-4 border-l-emerald-500">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-white">
                                    2
                                </span>
                                Variants
                            </CardTitle>
                            <CardDescription>Update variants. Removed variants go to trash.</CardDescription>
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
                                className="rounded-lg border p-4"
                            >
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="font-semibold">
                                        Variant #{vIndex + 1}
                                        {v.id && <span className="text-muted-foreground ml-2 text-xs">(ID: {v.id})</span>}
                                    </h3>
                                    {variants.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeVariant(vIndex)}
                                            className="text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>

                                {/* BARCODES */}
                                <div className="rounded-lg border border-dashed border-blue-300 bg-blue-50/40 p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Barcode className="h-4 w-4 text-blue-600" />
                                            <Label className="text-blue-900">Barcodes &amp; Stock</Label>
                                            {v.barcodes.length > 0 && (
                                                <Badge variant="secondary" className="text-xs">
                                                    {v.barcodes.length}
                                                </Badge>
                                            )}
                                        </div>
                                        <Button type="button" size="sm" variant="outline" onClick={() => addBarcode(vIndex, true)} className="gap-1">
                                            <Plus className="h-3 w-3" /> Add Barcode
                                        </Button>
                                    </div>

                                    {v.barcodes.length === 0 ? (
                                        <div className="flex flex-col items-center gap-2 py-4 text-center">
                                            <Scan className="text-muted-foreground h-8 w-8" />
                                            <p className="text-muted-foreground text-sm">No barcodes yet.</p>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => addBarcode(vIndex, true)}
                                                className="gap-1"
                                            >
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
                                                        className={`flex flex-col gap-2 rounded-md p-2 transition-colors ${
                                                            hasError ? 'bg-red-50 ring-2 ring-red-300' : ''
                                                        }`}
                                                    >
                                                        <div className="flex flex-wrap gap-2">
                                                            <div className="relative min-w-[200px] flex-1">
                                                                <Input
                                                                    ref={(el) => {
                                                                        barcodeInputRefs.current[refKey] = el;
                                                                    }}
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
                                                                            checkBarcodeExists(vIndex, bIndex, b.barcode);
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
                                                            <Select
                                                                value={b.barcode_type}
                                                                onValueChange={(val) => updateBarcode(vIndex, bIndex, 'barcode_type', val)}
                                                            >
                                                                <SelectTrigger className="w-[130px]">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {BARCODE_TYPES.map((t) => (
                                                                        <SelectItem key={t.value} value={t.value}>
                                                                            {t.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                            <div className="relative w-[100px]">
                                                                <Input
                                                                    type="number"
                                                                    min="0"
                                                                    onKeyDown={preventNegative}
                                                                    value={b.current_stock}
                                                                    onChange={(e) => {
                                                                        const val = e.target.value;
                                                                        if (toNum(val) < 0) return;
                                                                        updateBarcode(vIndex, bIndex, 'current_stock', val);
                                                                    }}
                                                                    placeholder="0"
                                                                    className="text-center"
                                                                />
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                variant={b.is_primary ? 'default' : 'ghost'}
                                                                size="icon"
                                                                onClick={() => updateBarcode(vIndex, bIndex, 'is_primary', true)}
                                                            >
                                                                <Star className={`h-4 w-4 ${b.is_primary ? 'fill-current' : ''}`} />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => removeBarcode(vIndex, bIndex)}
                                                            >
                                                                <X className="h-4 w-4" />
                                                            </Button>
                                                        </div>

                                                        {b._exists && (
                                                            <div className="flex items-center gap-1 pl-1 text-xs font-semibold text-red-600">
                                                                <AlertCircle className="h-3 w-3" />⚠{' '}
                                                                {b._existingProduct || 'This barcode already exists'}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>

                                {/* Specs */}
                                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Volume (ml)</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            onKeyDown={preventNegative}
                                            value={v.volume_ml}
                                            onChange={(e) => updateVariant(vIndex, 'volume_ml', e.target.value)}
                                            placeholder="100"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Concentration</Label>
                                        <Select value={v.concentration} onValueChange={(val) => updateVariant(vIndex, 'concentration', val)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {CONCENTRATIONS.map((c) => (
                                                    <SelectItem key={c.value} value={c.value}>
                                                        {c.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                {/* Prices */}
                                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label>Purchase Price (AFN)</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            onKeyDown={preventNegative}
                                            value={v.purchase_price_afn}
                                            onChange={(e) => updateVariant(vIndex, 'purchase_price_afn', e.target.value)}
                                            placeholder="6500"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>
                                            Sale Price (AFN) <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            onKeyDown={preventNegative}
                                            value={v.sale_price_afn}
                                            onChange={(e) => updateVariant(vIndex, 'sale_price_afn', e.target.value)}
                                            placeholder="9750"
                                            className={errors[`variants.${vIndex}.sale_price_afn`] ? 'border-destructive' : ''}
                                        />
                                        {errors[`variants.${vIndex}.sale_price_afn`] && (
                                            <p className="text-destructive text-xs">{errors[`variants.${vIndex}.sale_price_afn`]}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Wholesale Price (AFN)</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            onKeyDown={preventNegative}
                                            value={v.wholesale_price_afn}
                                            onChange={(e) => updateVariant(vIndex, 'wholesale_price_afn', e.target.value)}
                                            placeholder="8500"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Wholesale Min Qty</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            onKeyDown={preventNegative}
                                            value={v.wholesale_min_qty}
                                            onChange={(e) => updateVariant(vIndex, 'wholesale_min_qty', e.target.value)}
                                            placeholder="10"
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {/* ═══════════ STEP 3: Purchases ═══════════ */}
                <Card className="border-l-4 border-l-purple-500">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-purple-500 text-xs font-bold text-white">
                                    3
                                </span>
                                Purchases
                            </CardTitle>
                            <CardDescription>Update or add purchase records.</CardDescription>
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
                            const unitPrice = toNum(variant?.purchase_price_afn);

                            return (
                                <div
                                    key={p._key}
                                    data-error={errors[`purchases.${pIndex}.quantity`] ? 'true' : undefined}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="mb-4 flex items-center justify-between">
                                        <h3 className="flex items-center gap-2 font-semibold">
                                            <Receipt className="h-4 w-4" />
                                            Purchase #{pIndex + 1}
                                            {p.id && <span className="text-muted-foreground text-xs">(ID: {p.id})</span>}
                                        </h3>
                                        {purchases.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removePurchase(pIndex)}
                                                className="text-destructive"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>

                                    {/* Ownership & Quantity */}
                                    <div className="mb-4">
                                        <h4 className="mb-2 text-sm font-semibold text-gray-700">Ownership &amp; Quantity</h4>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label className="flex items-center gap-1">
                                                    <User className="h-3 w-3" />
                                                    Investor <span className="text-destructive">*</span>
                                                </Label>
                                                <Select value={p.investor_id} onValueChange={(val) => updatePurchase(pIndex, 'investor_id', val)}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="store">Shop</SelectItem>
                                                        {investors.map((inv) => (
                                                            <SelectItem key={inv.id} value={String(inv.id)}>
                                                                {inv.name}
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
                                                    onChange={(e) => {
                                                        const val = e.target.value;
                                                        if (toNum(val) < 0) return;
                                                        updatePurchase(pIndex, 'quantity', val);
                                                    }}
                                                    className={errors[`purchases.${pIndex}.quantity`] ? 'border-destructive' : ''}
                                                />
                                                {errors[`purchases.${pIndex}.quantity`] && (
                                                    <p className="text-destructive text-xs">{errors[`purchases.${pIndex}.quantity`]}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Profit & Summary */}
                                    <div className="mb-4">
                                        <h4 className="mb-2 flex items-center gap-1 text-sm font-semibold text-gray-700">
                                            <TrendingUp className="h-3 w-3" />
                                            Profit &amp; Summary
                                        </h4>

                                        {isInvestor ? (
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                                <div className="space-y-2">
                                                    <Label>
                                                        Investor Profit / Unit (AFN) <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        onKeyDown={preventNegative}
                                                        value={p.profit_per_unit}
                                                        onChange={(e) => {
                                                            const val = e.target.value;
                                                            if (toNum(val) < 0) return;
                                                            updatePurchase(pIndex, 'profit_per_unit', val);
                                                            recalcPurchase(pIndex);
                                                        }}
                                                        className={errors[`purchases.${pIndex}.profit_per_unit`] ? 'border-destructive' : ''}
                                                    />
                                                    {errors[`purchases.${pIndex}.profit_per_unit`] && (
                                                        <p className="text-destructive text-xs">{errors[`purchases.${pIndex}.profit_per_unit`]}</p>
                                                    )}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>Line Total (auto)</Label>
                                                    <div className="bg-muted/30 flex h-10 items-center rounded-md border border-dashed px-3 font-mono text-sm">
                                                        {p.line_total} <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                    </div>
                                                    <p className="text-muted-foreground text-xs">
                                                        = {unitPrice.toLocaleString()} × {p.quantity}
                                                    </p>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>Line Profit (auto)</Label>
                                                    <div className="flex h-10 items-center rounded-md border border-dashed bg-emerald-50 px-3 font-mono text-sm text-emerald-700">
                                                        {p.line_profit} <span className="ml-1 text-xs">USD</span>
                                                    </div>
                                                    <p className="text-muted-foreground text-xs">
                                                        = {p.profit_per_unit} × {p.quantity}
                                                    </p>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="space-y-3">
                                                <div className="rounded-lg border border-dashed border-emerald-300 bg-emerald-50/40 p-3">
                                                    <p className="text-xs text-emerald-900">
                                                        <strong>Store-owned purchase:</strong> The store's profit will be automatically calculated
                                                        from <em>sale price − purchase price</em> on the variant.
                                                    </p>
                                                </div>

                                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label>Line Total (auto)</Label>
                                                        <div className="bg-muted/30 flex h-10 items-center rounded-md border border-dashed px-3 font-mono text-sm">
                                                            {p.line_total} <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                        </div>
                                                        <p className="text-muted-foreground text-xs">
                                                            = {unitPrice.toLocaleString()} × {p.quantity}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Invoice & Date */}
                                    <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Invoice Number</Label>
                                            <Input
                                                value={p.invoice_number}
                                                onChange={(e) => updatePurchase(pIndex, 'invoice_number', e.target.value)}
                                                placeholder="e.g. INV-2024-001"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Purchase Date</Label>
                                            <Input
                                                type="date"
                                                value={p.purchase_date}
                                                onChange={(e) => updatePurchase(pIndex, 'purchase_date', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    {/* Supplier / Payment / Exchange */}
                                    <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div className="space-y-2">
                                            <Label>Supplier</Label>
                                            <Select value={p.supplier_id} onValueChange={(val) => updatePurchase(pIndex, 'supplier_id', val)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">No Supplier</SelectItem>
                                                    {suppliers.map((s) => (
                                                        <SelectItem key={s.id} value={String(s.id)}>
                                                            {s.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>USD to AFN</Label>
                                            <Input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                value={p.exchange_rate}
                                                onChange={(e) => updatePurchase(pIndex, 'exchange_rate', e.target.value)}
                                                placeholder="e.g. 64"
                                            />
                                            <p className="text-muted-foreground text-xs">Dollar rate on this purchase date</p>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Paid now (AFN)</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={p.paid_now}
                                                onChange={(e) => updatePurchase(pIndex, 'paid_now', e.target.value)}
                                                placeholder="0 if this bill is on credit"
                                            />
                                        </div>
                                    </div>

                                    {/* Bill Image */}
                                    <div className="mb-4 space-y-2">
                                        <Label>Bill Image</Label>
                                        {p.bill_image_url && !p.billImage && (
                                            <a
                                                href={`/storage/${p.bill_image_url}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-xs text-blue-600 underline"
                                            >
                                                View current bill
                                            </a>
                                        )}
                                        <Input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => updatePurchase(pIndex, 'billImage', e.target.files?.[0] || null)}
                                        />
                                        <p className="text-muted-foreground text-xs">Leave empty to keep current bill image.</p>
                                    </div>

                                    {/* Notes */}
                                    <div className="space-y-2">
                                        <Label>Notes</Label>
                                        <Textarea
                                            value={p.notes}
                                            onChange={(e) => updatePurchase(pIndex, 'notes', e.target.value)}
                                            rows={2}
                                            placeholder="Optional notes about this purchase"
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                {/* ═══════════ Submit ═══════════ */}
                <div className="flex gap-2">
                    <Button type="submit" disabled={loading} className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Saving...' : 'Update Product'}
                    </Button>
                    <Link href={`/products/${product.id}`}>
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
