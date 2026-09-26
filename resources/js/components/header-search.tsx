import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { LayoutGrid, Loader2, Package, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface PageResult {
    title: string;
    url: string;
    category: string;
}

interface ProductResult {
    product_id: number;
    product_name: string | null;
    brand_name: string | null;
    variant_label: string | null;
    barcode: string | null;
    sale_price: number;
    purchase_price: number | null;
    stock: number;
    url: string | null;
    url_edit: string | null;
}

const formatPrice = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value);

export function HeaderSearch() {
    const [query, setQuery] = useState('');
    const [pages, setPages] = useState<PageResult[]>([]);
    const [products, setProducts] = useState<ProductResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const boxRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const items = [
        ...pages.map((page) => ({ kind: 'page' as const, page })),
        ...products.map((product) => ({ kind: 'product' as const, product })),
    ];

    useEffect(() => {
        const onPointerDown = (event: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const onKey = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                inputRef.current?.focus();
                inputRef.current?.select();
            }
        };
        document.addEventListener('mousedown', onPointerDown);
        window.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            window.removeEventListener('keydown', onKey);
        };
    }, []);

    const search = async (value: string) => {
        if (value.trim().length < 2) {
            setPages([]);
            setProducts([]);
            setOpen(false);
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`/search?q=${encodeURIComponent(value.trim())}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                setPages([]);
                setProducts([]);
                setOpen(true);
                return;
            }
            const data = await response.json();
            setPages(data.pages ?? []);
            setProducts(data.products ?? []);
            setActive(0);
            setOpen(true);
        } catch {
            setPages([]);
            setProducts([]);
        } finally {
            setLoading(false);
        }
    };

    const visit = (url: string | null) => {
        if (!url) return;
        setOpen(false);
        setQuery('');
        router.visit(url);
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            setOpen(false);
            return;
        }
        if (!open || items.length === 0) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => (index + 1) % items.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => (index - 1 + items.length) % items.length);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const item = items[active];
            if (!item) return;
            visit(item.kind === 'page' ? item.page.url : item.product.url);
        }
    };

    return (
        <div ref={boxRef} className="relative w-full">
            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
            <Input
                ref={inputRef}
                value={query}
                onChange={(event) => {
                    const value = event.target.value;
                    setQuery(value);
                    if (timerRef.current) clearTimeout(timerRef.current);
                    timerRef.current = setTimeout(() => search(value), 250);
                }}
                onFocus={() => {
                    if (pages.length || products.length) setOpen(true);
                }}
                onKeyDown={onKeyDown}
                placeholder="Search pages or products"
                className="h-9 bg-background pr-16 pl-9"
                aria-label="Search"
            />
            <div className="text-muted-foreground absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                {query ? (
                    <button
                        type="button"
                        className="hover:text-foreground rounded p-0.5"
                        onClick={() => {
                            setQuery('');
                            setPages([]);
                            setProducts([]);
                            setOpen(false);
                            inputRef.current?.focus();
                        }}
                        aria-label="Clear search"
                    >
                        <X className="h-4 w-4" />
                    </button>
                ) : (
                    <kbd className="hidden rounded border px-1.5 py-0.5 text-[10px] sm:inline">Ctrl K</kbd>
                )}
            </div>

            {open && (
                <div className="bg-popover absolute top-full right-0 left-0 z-50 mt-2 max-h-96 overflow-y-auto rounded-lg border shadow-lg">
                    {items.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-center text-sm">
                            {loading ? 'Searching...' : 'No results in your access.'}
                        </p>
                    ) : (
                        <ul>
                            {items.map((item, index) => {
                                const selected = index === active;
                                if (item.kind === 'page') {
                                    return (
                                        <li key={`page-${item.page.url}`}>
                                            <button
                                                type="button"
                                                className={cn('flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm', selected && 'bg-accent')}
                                                onMouseEnter={() => setActive(index)}
                                                onClick={() => visit(item.page.url)}
                                            >
                                                <LayoutGrid className="text-muted-foreground h-4 w-4 shrink-0" />
                                                <span className="min-w-0 flex-1 truncate font-medium">{item.page.title}</span>
                                                <span className="text-muted-foreground text-xs">{item.page.category}</span>
                                            </button>
                                        </li>
                                    );
                                }

                                const product = item.product;
                                return (
                                    <li key={`product-${product.product_id}-${product.barcode ?? index}`}>
                                        <button
                                            type="button"
                                            className={cn(
                                                'flex w-full items-start gap-3 px-3 py-2.5 text-left text-sm',
                                                selected && 'bg-accent',
                                                !product.url && 'cursor-default',
                                            )}
                                            onMouseEnter={() => setActive(index)}
                                            onClick={() => visit(product.url)}
                                        >
                                            <Package className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {product.product_name}
                                                    {product.brand_name ? ` · ${product.brand_name}` : ''}
                                                </span>
                                                <span className="text-muted-foreground block truncate text-xs">
                                                    {[product.variant_label, product.barcode, `Stock ${product.stock}`].filter(Boolean).join(' · ')}
                                                </span>
                                            </span>
                                            <span className="shrink-0 text-right font-mono text-xs">
                                                <span className="block">{formatPrice(product.sale_price)}</span>
                                                {product.purchase_price !== null && (
                                                    <span className="text-muted-foreground block">Cost {formatPrice(product.purchase_price)}</span>
                                                )}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
