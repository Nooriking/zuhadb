import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { UserPlus, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
    { title: 'Create Investor', href: '/investors/create' },
];

/**
 * Investor identity is registered only in Users.
 * Backend redirects here to /users/create; this page is a safety fallback.
 */
export default function InvestorsCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Investor User" />
            <div className="mx-auto flex max-w-lg flex-col gap-6 p-6">
                <div className="flex items-center gap-3">
                    <Users className="text-primary h-6 w-6" />
                    <h1 className="text-2xl font-bold tracking-tight">No separate investor registration</h1>
                </div>
                <p className="text-muted-foreground text-sm leading-relaxed">
                    Create the person once under Users, assign the Investor role, then manage capital, goods, and
                    withdrawals from Investor Details. Do not enter the same name, phone, or email again here.
                </p>
                <div className="flex flex-wrap gap-2">
                    <Link href="/users/create">
                        <Button className="gap-2">
                            <UserPlus className="h-4 w-4" /> Create User (Investor role)
                        </Button>
                    </Link>
                    <Link href="/investors">
                        <Button variant="outline">Back to Investors</Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
