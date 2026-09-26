import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Banknote, Check, Edit, Eye, MoreHorizontal, Printer, Trash2, X } from 'lucide-react';
import { Children, createContext, useContext, type ReactNode } from 'react';

const RowActionsMode = createContext<'icons' | 'menu'>('icons');

export function RowActions({ children }: { children: ReactNode }) {
    const items = Children.toArray(children).filter((child) => child !== null && child !== undefined && child !== false);
    const mode = items.length > 3 ? 'menu' : 'icons';

    if (mode === 'menu') {
        return (
            <RowActionsMode.Provider value="menu">
                <div className="flex items-center justify-center">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button type="button" variant="ghost" size="icon" title="Actions">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {items}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </RowActionsMode.Provider>
        );
    }

    return (
        <RowActionsMode.Provider value="icons">
            <div className="flex flex-nowrap items-center justify-center">{items}</div>
        </RowActionsMode.Provider>
    );
}

export function RowAction({
    title,
    href,
    onClick,
    destructive,
    disabled,
    children,
}: {
    title: string;
    href?: string;
    onClick?: () => void;
    destructive?: boolean;
    disabled?: boolean;
    children: ReactNode;
}) {
    const mode = useContext(RowActionsMode);
    const tone = destructive ? 'text-destructive hover:text-destructive focus:text-destructive' : undefined;

    if (mode === 'menu') {
        if (href && !disabled) {
            return (
                <DropdownMenuItem asChild className={tone}>
                    <Link href={href}>
                        {children}
                        {title}
                    </Link>
                </DropdownMenuItem>
            );
        }

        return (
            <DropdownMenuItem onClick={onClick} disabled={disabled} className={tone}>
                {children}
                {title}
            </DropdownMenuItem>
        );
    }

    const className = cn(tone);

    if (href && !disabled) {
        return (
            <Button variant="ghost" size="icon" title={title} className={className} asChild>
                <Link href={href}>{children}</Link>
            </Button>
        );
    }

    return (
        <Button type="button" variant="ghost" size="icon" title={title} onClick={onClick} disabled={disabled} className={className}>
            {children}
        </Button>
    );
}

export function ViewAction({ href }: { href: string }) {
    return (
        <RowAction href={href} title="View">
            <Eye className="h-4 w-4" />
        </RowAction>
    );
}

export function EditAction({ href }: { href: string }) {
    return (
        <RowAction href={href} title="Edit">
            <Edit className="h-4 w-4" />
        </RowAction>
    );
}

export function DeleteAction({ onClick, title = 'Delete' }: { onClick: () => void; title?: string }) {
    return (
        <RowAction title={title} onClick={onClick} destructive>
            <Trash2 className="h-4 w-4" />
        </RowAction>
    );
}

export function PrintAction({ onClick }: { onClick: () => void }) {
    return (
        <RowAction title="Print" onClick={onClick}>
            <Printer className="h-4 w-4" />
        </RowAction>
    );
}

export function CompleteAction({ onClick }: { onClick: () => void }) {
    return (
        <RowAction title="Complete" onClick={onClick}>
            <Check className="h-4 w-4" />
        </RowAction>
    );
}

export function CancelAction({ onClick }: { onClick: () => void }) {
    return (
        <RowAction title="Cancel" onClick={onClick} destructive>
            <X className="h-4 w-4" />
        </RowAction>
    );
}

export function PayAction({ onClick, title = 'Pay' }: { onClick: () => void; title?: string }) {
    return (
        <RowAction title={title} onClick={onClick}>
            <Banknote className="h-4 w-4" />
        </RowAction>
    );
}
