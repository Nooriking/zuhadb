import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { User } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
    investor?: { id: number; full_name?: string; name?: string };
}

/**
 * Investor identity edits happen in Users. Backend redirects to users.edit;
 * this page is a safety fallback only.
 */
export default function InvestorsEdit({ investor }: Props) {
    const id = investor?.id;
    const name = investor?.full_name || investor?.name || 'Investor';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Investors', href: '/investors' },
        { title: name, href: id ? `/investors/${id}` : '/investors' },
        { title: 'Edit', href: id ? `/investors/${id}/edit` : '/investors' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${name}`} />
            <div className="mx-auto flex max-w-lg flex-col gap-6 p-6">
                <div className="flex items-center gap-3">
                    <User className="text-primary h-6 w-6" />
                    <h1 className="text-2xl font-bold tracking-tight">Edit identity in Users</h1>
                </div>
                <p className="text-muted-foreground text-sm leading-relaxed">
                    Name, phone, email, and account status are managed on the Users module. Investor Details is for
                    capital, goods, profit, and withdrawals only.
                </p>
                <div className="flex flex-wrap gap-2">
                    {id && (
                        <Link href={`/users/${id}/edit`}>
                            <Button className="gap-2">
                                <User className="h-4 w-4" /> Open User Edit
                            </Button>
                        </Link>
                    )}
                    <Link href={id ? `/investors/${id}` : '/investors'}>
                        <Button variant="outline">Back</Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
