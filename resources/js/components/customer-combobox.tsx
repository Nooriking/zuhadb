// resources/js/components/customer-combobox.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Phone, Search, User, UserPlus, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address?: string | null;
}

interface Props {
    customers: Customer[];
    value: string;
    onChange: (value: string) => void;
    error?: boolean;
    placeholder?: string;
    disabled?: boolean;
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

function highlightText(text: string, search: string): React.ReactNode {
    if (!search.trim() || !text) return text;

    const term = search.trim();
    const index = text.toLowerCase().indexOf(term.toLowerCase());

    if (index === -1) return text;

    return (
        <>
            {text.slice(0, index)}
            <mark className="rounded bg-yellow-200 px-0.5 text-yellow-900 dark:bg-yellow-900/40 dark:text-yellow-100">
                {text.slice(index, index + term.length)}
            </mark>
            {text.slice(index + term.length)}
        </>
    );
}

function matchScore(customer: Customer, term: string): number {
    if (!term) return 0;

    const t = term.toLowerCase();
    const digits = t.replace(/\D+/g, '');
    const name = (customer.name || '').toLowerCase();
    const phone = (customer.phone || '').toLowerCase();
    const phoneDigits = phone.replace(/\D+/g, '');

    let score = 0;

    if (name === t) score += 100;
    else if (name.startsWith(t)) score += 50;
    else if (name.includes(t)) score += 20;

    if (phone === t || (digits !== '' && phoneDigits === digits)) score += 100;
    else if (phone.startsWith(t) || (digits !== '' && phoneDigits.startsWith(digits))) score += 50;
    else if (phone.includes(t) || (digits !== '' && phoneDigits.includes(digits))) score += 20;

    return score;
}

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export function CustomerCombobox({
    customers,
    value,
    onChange,
    error = false,
    placeholder = 'Select or search customer...',
    disabled = false,
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    /* ─── Selected customer ─── */
    const selectedCustomer = useMemo(() => customers.find((c) => String(c.id) === value), [customers, value]);

    /* ─── Filtered & sorted customers ─── */
    const filteredCustomers = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            const sorted = [...customers];

            if (selectedCustomer) {
                const idx = sorted.findIndex((c) => c.id === selectedCustomer.id);
                if (idx > 0) {
                    const [s] = sorted.splice(idx, 1);
                    sorted.unshift(s);
                }
            }

            return sorted;
        }

        return customers
            .map((c) => ({ customer: c, score: matchScore(c, term) }))
            .filter((item) => item.score > 0)
            .sort((a, b) => b.score - a.score)
            .map((item) => item.customer);
    }, [customers, search, selectedCustomer]);

    /* ─── Reset search when closed ─── */
    useEffect(() => {
        if (!open) {
            setSearch('');
        } else {
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    /* ─── Handlers ─── */

    const handleSelect = (customerId: string) => {
        onChange(customerId);
        setOpen(false);
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        e.preventDefault();
        onChange('none');
    };

    const handleNewCustomer = () => {
        onChange('new');
        setOpen(false);
    };

    /* ─── Display label ─── */
    const getDisplayLabel = () => {
        if (value === 'new') return 'Create new customer';
        if (value === 'none' || !value) return placeholder;

        if (selectedCustomer) {
            return selectedCustomer.phone ? `${selectedCustomer.name} — ${selectedCustomer.phone}` : selectedCustomer.name;
        }

        return placeholder;
    };

    /* ═══════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════ */

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        'h-10 w-full justify-between font-normal',
                        error && 'border-destructive',
                        value === 'none' && 'text-muted-foreground',
                    )}
                >
                    <div className="flex items-center gap-2 truncate">
                        {value === 'new' ? (
                            <UserPlus className="h-4 w-4 shrink-0 text-blue-600" />
                        ) : selectedCustomer ? (
                            <User className="text-primary h-4 w-4 shrink-0" />
                        ) : (
                            <Search className="text-muted-foreground h-4 w-4 shrink-0" />
                        )}
                        <span className="truncate">{getDisplayLabel()}</span>
                    </div>

                    <div className="flex items-center gap-1">
                        {value && value !== 'none' && (
                            <span
                                role="button"
                                tabIndex={0}
                                onClick={handleClear}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        handleClear(e as any);
                                    }
                                }}
                                className="hover:bg-muted flex h-5 w-5 cursor-pointer items-center justify-center rounded"
                                title="Clear"
                            >
                                <X className="h-3 w-3" />
                            </span>
                        )}
                        <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
                    </div>
                </Button>
            </PopoverTrigger>

            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start" sideOffset={4}>
                <div className="flex flex-col">
                    {/* ═══ Search Input ═══ */}
                    <div className="flex items-center border-b px-3">
                        <Search className="text-muted-foreground mr-2 h-4 w-4 shrink-0" />
                        <Input
                            ref={inputRef}
                            placeholder="Search by name or phone..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-11 border-0 bg-transparent px-0 shadow-none focus-visible:ring-0"
                        />
                        {search && (
                            <button type="button" onClick={() => setSearch('')} className="text-muted-foreground hover:text-foreground">
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>

                    {/* ═══ Results ═══ */}
                    <div className="max-h-[320px] overflow-y-auto p-1">
                        {/* Actions */}
                        <div className="px-1 py-1">
                            <p className="text-muted-foreground px-2 py-1 text-xs font-semibold tracking-wide uppercase">Actions</p>

                            <button
                                type="button"
                                onClick={handleNewCustomer}
                                className="hover:bg-accent flex w-full items-center gap-2 rounded-sm px-2 py-2 text-left text-sm transition"
                            >
                                <div className="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-full">
                                    <UserPlus className="text-primary h-4 w-4" />
                                </div>
                                <div className="flex-1">
                                    <p className="font-medium">Create new customer</p>
                                    <p className="text-muted-foreground text-xs">Fill in the fields below</p>
                                </div>
                                {value === 'new' && <Check className="text-primary ml-2 h-4 w-4" />}
                            </button>

                            <button
                                type="button"
                                onClick={() => handleSelect('none')}
                                className="hover:bg-accent flex w-full items-center gap-2 rounded-sm px-2 py-2 text-left text-sm transition"
                            >
                                <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-full">
                                    <X className="text-muted-foreground h-4 w-4" />
                                </div>
                                <div className="flex-1">
                                    <p className="font-medium">No customer (walk-in)</p>
                                    <p className="text-muted-foreground text-xs">Sale without customer record</p>
                                </div>
                                {value === 'none' && <Check className="text-primary ml-2 h-4 w-4" />}
                            </button>
                        </div>

                        {/* Customers */}
                        <div className="mt-1 border-t px-1 py-1">
                            <p className="text-muted-foreground px-2 py-1 text-xs font-semibold tracking-wide uppercase">
                                {search ? `Customers (${filteredCustomers.length})` : `All Customers (${filteredCustomers.length})`}
                            </p>

                            {filteredCustomers.length === 0 ? (
                                <div className="px-2 py-6 text-center">
                                    <Search className="text-muted-foreground mx-auto h-6 w-6" />
                                    <p className="text-muted-foreground mt-2 text-sm">No customer found for "{search}"</p>
                                    <Button type="button" variant="default" size="sm" onClick={handleNewCustomer} className="mt-3 gap-1">
                                        <UserPlus className="h-3 w-3" />
                                        Create "{search}"
                                    </Button>
                                </div>
                            ) : (
                                filteredCustomers.map((customer) => {
                                    const isSelected = String(customer.id) === value;

                                    return (
                                        <button
                                            key={customer.id}
                                            type="button"
                                            onClick={() => handleSelect(String(customer.id))}
                                            className="hover:bg-accent flex w-full items-center gap-2 rounded-sm px-2 py-2 text-left text-sm transition"
                                        >
                                            <div
                                                className={cn(
                                                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                                    isSelected ? 'bg-primary/10' : 'bg-muted',
                                                )}
                                            >
                                                <User className={cn('h-4 w-4', isSelected && 'text-primary')} />
                                            </div>

                                            <div className="ml-2 flex flex-1 flex-col gap-0.5 overflow-hidden">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-medium">
                                                        {highlightText(customer.name || '—', search)}
                                                    </span>
                                                    {isSelected && (
                                                        <Badge variant="outline" className="border-primary text-primary shrink-0 text-[10px]">
                                                            Selected
                                                        </Badge>
                                                    )}
                                                </div>
                                                {customer.phone && (
                                                    <div className="text-muted-foreground flex items-center gap-1 text-xs">
                                                        <Phone className="h-3 w-3 shrink-0" />
                                                        <span className="truncate font-mono">{highlightText(customer.phone, search)}</span>
                                                    </div>
                                                )}
                                            </div>

                                            {isSelected && <Check className="text-primary ml-2 h-4 w-4 shrink-0" />}
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
