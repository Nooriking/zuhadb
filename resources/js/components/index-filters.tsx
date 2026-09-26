import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Search, X } from 'lucide-react';
import { type ReactNode } from 'react';

export const filterControlClass =
    'flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring';

export function FilterField({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <label className={cn('flex min-w-0 flex-col gap-1.5', className)}>
            <span className="text-muted-foreground text-xs font-medium">{label}</span>
            {children}
        </label>
    );
}

export function IndexFilters({
    search,
    onSearchChange,
    onSearch,
    onClear,
    placeholder = 'Search',
    showClear = false,
    children,
}: {
    search?: string;
    onSearchChange?: (value: string) => void;
    onSearch?: () => void;
    onClear?: () => void;
    placeholder?: string;
    showClear?: boolean;
    children?: ReactNode;
}) {
    return (
        <div className="bg-card rounded-lg border p-4">
            <div className="flex flex-col gap-3">
                {onSearchChange && (
                    <div className="flex flex-col gap-1.5">
                        <span className="text-muted-foreground text-xs font-medium">Search</span>
                        <div className="flex gap-2">
                            <Input
                                value={search ?? ''}
                                onChange={(e) => onSearchChange(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        onSearch?.();
                                    }
                                }}
                                placeholder={placeholder}
                                className="bg-background"
                            />
                            <Button type="button" variant="outline" onClick={onSearch} className="shrink-0 gap-2">
                                <Search className="h-4 w-4" /> Search
                            </Button>
                            {showClear && onClear && (
                                <Button type="button" variant="ghost" onClick={onClear} className="shrink-0 gap-2">
                                    <X className="h-4 w-4" /> Clear
                                </Button>
                            )}
                        </div>
                    </div>
                )}
                {children && <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">{children}</div>}
                {!onSearchChange && (onSearch || (showClear && onClear)) && (
                    <div className="flex gap-2">
                        {onSearch && (
                            <Button type="button" variant="outline" onClick={onSearch} className="gap-2">
                                <Search className="h-4 w-4" /> Search
                            </Button>
                        )}
                        {showClear && onClear && (
                            <Button type="button" variant="ghost" onClick={onClear} className="gap-2">
                                <X className="h-4 w-4" /> Clear
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
