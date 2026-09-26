// resources/js/components/product-search-bar.tsx

import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, Barcode as BarcodeIcon, Boxes, Edit, Eye, Loader2, Package, Search, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface SearchResult {
    type: 'barcode' | 'product';
    product_id: number | null;
    product_name: string | null;
    brand_name: string | null;
    variant_id: number | null;
    variant_label: string | null;
    barcode: string | null;
    barcode_type: string | null;
    is_primary: boolean;
    purchase_price: number;
    sale_price: number;
    wholesale_price: number;
    stock: number;
    variants_count?: number;
    url_product: string | null;
    url_inventory: string | null;
    url_edit: string | null;
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v || 0);

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export function ProductSearchBar() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [highlightIndex, setHighlightIndex] = useState(-1);

    const inputRef = useRef<HTMLInputElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    /* ─── Click outside → close ─── */
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
                setHighlightIndex(-1);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    /* ─── Keyboard shortcut: Ctrl+K / Cmd+K ─── */
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
                inputRef.current?.select();
            }
            if (e.key === 'Escape') {
                setOpen(false);
                setHighlightIndex(-1);
                inputRef.current?.blur();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    /* ─── Debounced search ─── */
    const performSearch = useCallback(async (q: string) => {
        if (q.trim().length < 2) {
            setResults([]);
            setOpen(false);
            return;
        }

        setLoading(true);

        try {
            const res = await fetch(`/products/search?q=${encodeURIComponent(q)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (res.ok) {
                const data = await res.json();
                setResults(data.results || []);
                setOpen(true);
                setHighlightIndex(data.results?.length > 0 ? 0 : -1);
            } else {
                setResults([]);
            }
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }, []);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setQuery(val);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            performSearch(val);
        }, 300);
    };

    /* ─── Keyboard navigation ─── */
    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!open || results.length === 0) {
            if (e.key === 'Enter' && query.trim().length >= 2) {
                performSearch(query);
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightIndex((prev) => (prev + 1) % results.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightIndex((prev) => (prev - 1 + results.length) % results.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlightIndex >= 0 && results[highlightIndex]) {
                const r = results[highlightIndex];
                if (r.url_product) {
                    router.visit(r.url_product);
                }
                setOpen(false);
            }
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    const clearSearch = () => {
        setQuery('');
        setResults([]);
        setOpen(false);
        setHighlightIndex(-1);
        inputRef.current?.focus();
    };

    /* ═══════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════ */

    return (
        <div ref={containerRef} className="relative w-full max-w-md">
            {/* Input */}
            <div className="relative">
                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input
                    ref={inputRef}
                    type="text"
                    value={query}
                    onChange={handleChange}
                    onKeyDown={handleKeyDown}
                    onFocus={() => {
                        if (results.length > 0) setOpen(true);
                    }}
                    placeholder="Search product, barcode, brand... (Ctrl+K)"
                    className="h-10 pr-10 pl-9"
                />

                {/* Loading / Clear */}
                <div className="absolute top-1/2 right-2 -translate-y-1/2">
                    {loading ? (
                        <Loader2 className="text-muted-foreground h-4 w-4 animate-spin" />
                    ) : query ? (
                        <button type="button" onClick={clearSearch} className="text-muted-foreground hover:text-foreground">
                            <X className="h-4 w-4" />
                        </button>
                    ) : null}
                </div>
            </div>

            {/* Dropdown */}
            {open && (
                <div className="bg-popover absolute top-full right-0 left-0 z-50 mt-2 max-h-[480px] overflow-y-auto rounded-lg border shadow-lg">
                    {results.length === 0 ? (
                        <div className="text-muted-foreground px-4 py-8 text-center text-sm">
                            {loading ? 'Searching...' : `No results for "${query}"`}
                        </div>
                    ) : (
                        <div className="divide-y">
                            {results.map((r, idx) => {
                                const isHighlighted = idx === highlightIndex;
                                const isLowStock = r.stock > 0 && r.stock <= 5;
                                const isOutOfStock = r.stock <= 0;

                                return (
                                    <div
                                        key={`${r.type}-${r.product_id}-${r.variant_id}-${r.barcode}-${idx}`}
                                        className={cn(
                                            'cursor-pointer p-3 transition-colors',
                                            isHighlighted ? 'bg-amber-50 dark:bg-amber-950/30' : 'hover:bg-muted/50',
                                        )}
                                        onClick={() => {
                                            if (r.url_product) {
                                                router.visit(r.url_product);
                                                setOpen(false);
                                            }
                                        }}
                                        onMouseEnter={() => setHighlightIndex(idx)}
                                    >
                                        {/* Row 1: Name + Brand + Type */}
                                        <div className="mb-1.5 flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <Package className="text-muted-foreground h-4 w-4 shrink-0" />
                                                    <span className="truncate text-sm font-semibold">{r.product_name || '—'}</span>
                                                    {r.brand_name && (
                                                        <Badge variant="outline" className="text-[10px]">
                                                            {r.brand_name}
                                                        </Badge>
                                                    )}
                                                </div>

                                                {r.variant_label && (
                                                    <div className="text-muted-foreground mt-0.5 ml-6 text-xs">
                                                        {r.variant_label}
                                                        {r.variants_count && r.variants_count > 1 && (
                                                            <span className="ml-1">+{r.variants_count - 1} more variants</span>
                                                        )}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Stock Badge */}
                                            <div className="shrink-0">
                                                {isOutOfStock ? (
                                                    <Badge variant="destructive" className="gap-1 text-[10px]">
                                                        <AlertTriangle className="h-3 w-3" /> Out
                                                    </Badge>
                                                ) : isLowStock ? (
                                                    <Badge variant="outline" className="gap-1 border-amber-500 text-[10px] text-amber-700">
                                                        <AlertTriangle className="h-3 w-3" /> {r.stock}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="text-[10px]">
                                                        Stock: {r.stock}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>

                                        {/* Row 2: Barcode */}
                                        {r.barcode && (
                                            <div className="mb-1.5 flex items-center gap-2 pl-6 font-mono text-xs">
                                                <BarcodeIcon className="text-muted-foreground h-3 w-3" />
                                                <span>{r.barcode}</span>
                                                {r.barcode_type && <span className="text-muted-foreground">({r.barcode_type})</span>}
                                                {r.is_primary && <Badge className="bg-blue-600 text-[9px]">Primary</Badge>}
                                            </div>
                                        )}

                                        {/* Row 3: Prices */}
                                        <div className="ml-6 flex flex-wrap gap-3 text-xs">
                                            <div>
                                                <span className="text-muted-foreground">Buy:</span>{' '}
                                                <span className="font-mono font-semibold">{formatPrice(r.purchase_price)}</span>
                                                <span className="text-muted-foreground ml-0.5 text-[10px]">AFN</span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Sale:</span>{' '}
                                                <span className="font-mono font-semibold text-green-700">{formatPrice(r.sale_price)}</span>
                                                <span className="text-muted-foreground ml-0.5 text-[10px]">AFN</span>
                                            </div>
                                            {r.wholesale_price > 0 && (
                                                <div>
                                                    <span className="text-muted-foreground">Whole:</span>{' '}
                                                    <span className="font-mono">{formatPrice(r.wholesale_price)}</span>
                                                </div>
                                            )}
                                        </div>

                                        {/* Row 4: Actions */}
                                        <div className="mt-2 ml-6 flex flex-wrap gap-1">
                                            {r.url_product && (
                                                <Link
                                                    href={r.url_product}
                                                    className="hover:bg-muted inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px]"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <Eye className="h-3 w-3" /> View
                                                </Link>
                                            )}
                                            {r.url_edit && (
                                                <Link
                                                    href={r.url_edit}
                                                    className="hover:bg-muted inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px]"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <Edit className="h-3 w-3" /> Edit
                                                </Link>
                                            )}
                                            {r.url_inventory && (
                                                <Link
                                                    href={r.url_inventory}
                                                    className="hover:bg-muted inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px]"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <Boxes className="h-3 w-3" /> Inventory
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
