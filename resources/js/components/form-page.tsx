import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type ReactNode } from 'react';

export function BackButton({ href, label = 'Back' }: { href: string; label?: string }) {
    return (
        <Button variant="outline" asChild className="border-border bg-card text-foreground hover:bg-muted">
            <Link href={href}>
                <ArrowLeft className="h-4 w-4" />
                {label}
            </Link>
        </Button>
    );
}

export function FormPageHeader({
    title,
    backHref,
    description,
    extra,
}: {
    title: string;
    backHref: string;
    description?: string;
    extra?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
                <BackButton href={backHref} />
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                    {description ? <p className="text-muted-foreground text-sm">{description}</p> : null}
                </div>
            </div>
            {extra}
        </div>
    );
}

export function FormActions({
    backHref,
    submitLabel,
    loading = false,
}: {
    backHref: string;
    submitLabel: string;
    loading?: boolean;
}) {
    return (
        <div className="bg-card flex flex-wrap items-center justify-end gap-2 rounded-lg border p-4">
            <BackButton href={backHref} label="Cancel" />
            <Button type="submit" disabled={loading}>
                {loading ? 'Saving...' : submitLabel}
            </Button>
        </div>
    );
}
