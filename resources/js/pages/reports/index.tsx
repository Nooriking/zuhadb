import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    ArrowRight,
    BarChart3,
    Building2,
    CircleDot,
    ClipboardList,
    Download,
    Lock,
    Package,
    Search,
    ShoppingBag,
    Truck,
    UserCircle,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo, useState, type ComponentType } from 'react';

type ReportItem = {
    key: string;
    title: string;
    description: string;
    href: string | null;
    module: string | null;
    status: 'available' | 'not_implemented';
    accessible: boolean;
};

type ReportCategory = {
    key: string;
    title: string;
    description: string;
    reports: ReportItem[];
};

type Props = {
    categories: ReportCategory[];
    stats: {
        available: number;
        planned: number;
        categories: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
];

const categoryIcons: Record<string, ComponentType<{ className?: string }>> = {
    overview: ClipboardList,
    sales: ShoppingBag,
    financial: Wallet,
    inventory: Package,
    purchase: Truck,
    investor: Users,
    customer: UserCircle,
    supplier: Truck,
    'branch-staff': Building2,
};

export default function ReportsIndex({ categories, stats }: Props) {
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<'all' | 'available' | 'planned'>('all');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return categories
            .map((category) => {
                const reports = category.reports.filter((report) => {
                    if (filter === 'available' && !(report.status === 'available' && report.accessible)) {
                        return false;
                    }
                    if (filter === 'planned' && report.status === 'available' && report.accessible) {
                        return false;
                    }
                    if (!q) return true;
                    return (
                        report.title.toLowerCase().includes(q) ||
                        report.description.toLowerCase().includes(q) ||
                        category.title.toLowerCase().includes(q)
                    );
                });

                return { ...category, reports };
            })
            .filter((category) => category.reports.length > 0);
    }, [categories, filter, query]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <BarChart3 className="size-4" />
                            Reporting
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">Reports Overview</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">
                            Open implemented reports from here. Use Monthly &amp; Custom PDF Export for period business
                            summaries. Planned reports are listed for roadmap visibility.
                        </p>
                    </div>

                    <div className="flex flex-col items-stretch gap-2 sm:items-end">
                        <Button asChild>
                            <Link href="/reports/export">
                                <Download className="me-2 size-4" />
                                Export PDF report
                            </Link>
                        </Button>
                    <div className="flex flex-wrap gap-2 text-sm">
                        <span className="bg-background rounded-md border px-3 py-1.5">
                            <span className="text-muted-foreground">Available </span>
                            <span className="font-medium">{stats.available}</span>
                        </span>
                        <span className="bg-background rounded-md border px-3 py-1.5">
                            <span className="text-muted-foreground">Not yet implemented </span>
                            <span className="font-medium">{stats.planned}</span>
                        </span>
                        <span className="bg-background rounded-md border px-3 py-1.5">
                            <span className="text-muted-foreground">Categories </span>
                            <span className="font-medium">{stats.categories}</span>
                        </span>
                    </div>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search reports…"
                            className="ps-9"
                            aria-label="Search reports"
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(
                            [
                                ['all', 'All'],
                                ['available', 'Available'],
                                ['planned', 'Not available'],
                            ] as const
                        ).map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setFilter(value)}
                                className={`rounded-md border px-3 py-2 text-sm transition-colors ${
                                    filter === value
                                        ? 'bg-primary text-primary-foreground border-primary'
                                        : 'bg-background hover:bg-muted'
                                }`}
                                aria-pressed={filter === value}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {filtered.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed px-6 py-16 text-center text-sm">
                        No reports match your search or filter.
                    </div>
                ) : (
                    <div className="space-y-8">
                        {filtered.map((category) => {
                            const Icon = categoryIcons[category.key] ?? CircleDot;

                            return (
                                <section key={category.key} className="space-y-3">
                                    <div className="flex items-start gap-3">
                                        <div className="bg-muted flex size-9 shrink-0 items-center justify-center rounded-md">
                                            <Icon className="size-4" />
                                        </div>
                                        <div className="min-w-0">
                                            <h2 className="text-lg font-semibold">{category.title}</h2>
                                            <p className="text-muted-foreground text-sm">{category.description}</p>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        {category.reports.map((report) => {
                                            const available = report.status === 'available' && report.accessible;
                                            const lockedImplemented =
                                                report.status === 'available' && !report.accessible;

                                            const body = (
                                                <>
                                                    <div className="flex items-start justify-between gap-3">
                                                        <h3 className="font-medium leading-snug">{report.title}</h3>
                                                        {available ? (
                                                            <Badge variant="default" className="shrink-0">
                                                                Available
                                                            </Badge>
                                                        ) : lockedImplemented ? (
                                                            <Badge variant="secondary" className="shrink-0">
                                                                <Lock className="me-1 size-3" />
                                                                Restricted
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="shrink-0">
                                                                Not available
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                                        {report.description}
                                                    </p>
                                                    {available && (
                                                        <div className="text-primary mt-3 inline-flex items-center gap-1 text-sm font-medium">
                                                            Open report
                                                            <ArrowRight className="size-3.5 rtl:rotate-180" />
                                                        </div>
                                                    )}
                                                    {!available && !lockedImplemented && (
                                                        <p className="text-muted-foreground mt-3 text-xs">
                                                            Not yet implemented
                                                        </p>
                                                    )}
                                                    {lockedImplemented && (
                                                        <p className="text-muted-foreground mt-3 text-xs">
                                                            You do not have access to this report.
                                                        </p>
                                                    )}
                                                </>
                                            );

                                            if (available && report.href) {
                                                return (
                                                    <Link
                                                        key={report.key}
                                                        href={report.href}
                                                        className="bg-background hover:border-primary/40 focus-visible:ring-ring block rounded-lg border p-4 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                                    >
                                                        {body}
                                                    </Link>
                                                );
                                            }

                                            return (
                                                <div
                                                    key={report.key}
                                                    className="bg-muted/30 text-muted-foreground rounded-lg border border-dashed p-4"
                                                    aria-disabled
                                                >
                                                    {body}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </section>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
