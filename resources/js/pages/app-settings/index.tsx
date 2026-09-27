// resources/js/pages/app-settings/index.tsx

import { IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, Edit, Eye, Image as ImageIcon, Mail, Phone, Plus, Settings as SettingsIcon } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Setting {
    id: number;
    name: string;
    company_name: string | null;
    company_name_local: string | null;
    logo: string | null;
    logo_url?: string | null;
    phone: string | null;
    phone_2: string | null;
    email: string | null;
    website: string | null;
    address: string | null;
    created_at: string;
}

interface Props {
    settings: {
        data: Setting[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    hasSettings: boolean;
    filters: {
        search: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'App Settings', href: '/app-settings' },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function AppSettingsIndex({ settings, hasSettings, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = () => {
        router.get('/app-settings', { search: search || undefined }, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="App Settings" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <SettingsIcon className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">App Settings</h1>
                        <Badge variant="outline">{settings.total} Settings</Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {/* ⭐ Create button — only if no settings exist */}
                        {!hasSettings && (
                            <Link href="/app-settings/create">
                                <Button className="gap-2">
                                    <Plus className="h-4 w-4" /> New Setting
                                </Button>
                            </Link>
                        )}

                        {/* ⭐ Edit button — only if settings exist */}
                        {hasSettings && settings.data.length > 0 && (
                            <Link href={`/app-settings/${settings.data[0].id}/edit`}>
                                <Button className="gap-2">
                                    <Edit className="h-4 w-4" /> Edit Settings
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* ⭐ Warning — if settings exist */}
                {hasSettings && (
                    <div className="rounded-lg border-2 border-amber-200 bg-amber-50 p-4">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                            <div className="text-sm">
                                <p className="font-semibold text-amber-900">A setting already exists</p>
                                <p className="mt-0.5 text-amber-700">
                                    To change settings, use the "Edit Settings" button. Creating new settings is not allowed.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {hasSettings && (
                    <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by name, email, phone..." />
                )}

                {/* ═══════════ Table ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle>Settings</CardTitle>
                        <CardDescription>Company information</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-16">#</TableHead>
                                        <TableHead>Logo</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Company</TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Address</TableHead>
                                        <TableHead className="text-center">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {settings.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={8} className="text-muted-foreground h-24 text-center">
                                                No settings found. Click "New Setting" to create one.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        settings.data.map((setting, index) => (
                                            <TableRow key={setting.id}>
                                                <TableCell>{settings.from ? settings.from + index : index + 1}</TableCell>
                                                <TableCell>
                                                    {setting.logo_url ? (
                                                        <img loading="lazy" decoding="async"
                                                            src={setting.logo_url}
                                                            alt={setting.name}
                                                            className="h-12 w-12 rounded border bg-white object-contain p-1"
                                                        />
                                                    ) : (
                                                        <div className="flex h-12 w-12 items-center justify-center rounded border bg-gray-50">
                                                            <ImageIcon className="text-muted-foreground h-4 w-4" />
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="font-semibold">{setting.name || '—'}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">{setting.company_name || '—'}</div>
                                                    {setting.company_name_local && (
                                                        <div className="text-muted-foreground text-xs">{setting.company_name_local}</div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {setting.phone ? (
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <Phone className="h-3 w-3" />
                                                            <span className="font-mono">{setting.phone}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {setting.email ? (
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <Mail className="h-3 w-3" />
                                                            <span>{setting.email}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-muted-foreground max-w-xs truncate text-xs">{setting.address || '—'}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Link href={`/app-settings/${setting.id}`}>
                                                            <Button variant="ghost" size="icon" title="View">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/app-settings/${setting.id}/edit`}>
                                                            <Button variant="ghost" size="icon" title="Edit">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {settings.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-muted-foreground text-sm">
                                    Showing {settings.from || 0} to {settings.to || 0} of {settings.total} results
                                </div>
                                <div className="flex gap-1">
                                    {settings.links.map((link, i) => (
                                        <Button
                                            key={i}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => handlePageChange(link.url)}
                                            disabled={!link.url}
                                            className="min-w-[40px]"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
