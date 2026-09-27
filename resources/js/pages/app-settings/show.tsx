// resources/js/pages/app-settings/show.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Edit,
    Facebook,
    Globe,
    Instagram,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    Send,
    Settings as SettingsIcon,
} from 'lucide-react';

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
    social_facebook: string | null;
    social_instagram: string | null;
    social_whatsapp: string | null;
    social_telegram: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    setting: Setting;
}

const breadcrumbs = (setting: Setting): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'App Settings', href: '/app-settings' },
    { title: setting.name, href: `/app-settings/${setting.id}` },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function AppSettingsShow({ setting }: Props) {
    const hasSocial = setting.social_facebook || setting.social_instagram || setting.social_whatsapp || setting.social_telegram;

    return (
        <AppLayout breadcrumbs={breadcrumbs(setting)}>
            <Head title={`Settings: ${setting.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <SettingsIcon className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Setting Details</h1>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/app-settings/${setting.id}/edit`}>
                            <Button variant="outline" className="gap-2">
                                <Edit className="h-4 w-4" /> Edit
                            </Button>
                        </Link>
                        <Link href="/app-settings">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Company Card */}
                <Card className="border-primary/20 from-primary/5 border-2 bg-gradient-to-br to-transparent">
                    <CardContent className="flex flex-col items-center gap-6 p-6 md:flex-row">
                        {setting.logo_url ? (
                            <img loading="lazy" decoding="async"
                                src={setting.logo_url}
                                alt={setting.name}
                                className="h-28 w-28 rounded-lg border bg-white object-contain p-2 shadow-sm"
                            />
                        ) : (
                            <div className="flex h-28 w-28 items-center justify-center rounded-lg border-2 border-dashed bg-gray-50">
                                <Building2 className="text-muted-foreground h-12 w-12" />
                            </div>
                        )}
                        <div className="flex-1 text-center md:text-left">
                            <h2 className="text-3xl font-bold">{setting.name}</h2>
                            {setting.company_name && <p className="text-muted-foreground mt-1 text-lg">{setting.company_name}</p>}
                            {setting.company_name_local && <p className="text-muted-foreground text-sm">{setting.company_name_local}</p>}
                        </div>
                    </CardContent>
                </Card>

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {/* Phone */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="mb-1 flex items-center gap-2">
                            <Phone className="text-primary h-4 w-4" />
                            <p className="text-muted-foreground text-xs font-medium uppercase">Phone</p>
                        </div>
                        <p className="font-mono text-sm font-semibold">{setting.phone || '—'}</p>
                        {setting.phone_2 && <p className="text-muted-foreground font-mono text-xs">{setting.phone_2}</p>}
                    </div>

                    {/* Email */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="mb-1 flex items-center gap-2">
                            <Mail className="text-primary h-4 w-4" />
                            <p className="text-muted-foreground text-xs font-medium uppercase">Email</p>
                        </div>
                        {setting.email ? (
                            <a href={`mailto:${setting.email}`} className="text-sm font-semibold break-all text-blue-600 hover:underline">
                                {setting.email}
                            </a>
                        ) : (
                            <p className="text-muted-foreground text-sm">—</p>
                        )}
                    </div>

                    {/* Website */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="mb-1 flex items-center gap-2">
                            <Globe className="text-primary h-4 w-4" />
                            <p className="text-muted-foreground text-xs font-medium uppercase">Website</p>
                        </div>
                        {setting.website ? (
                            <a
                                href={setting.website}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm font-semibold break-all text-blue-600 hover:underline"
                            >
                                {setting.website}
                            </a>
                        ) : (
                            <p className="text-muted-foreground text-sm">—</p>
                        )}
                    </div>

                    {/* Address */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="mb-1 flex items-center gap-2">
                            <MapPin className="text-primary h-4 w-4" />
                            <p className="text-muted-foreground text-xs font-medium uppercase">Address</p>
                        </div>
                        <p className="text-sm">{setting.address || '—'}</p>
                    </div>
                </div>

                {/* Social Media */}
                {hasSocial && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Social Media</CardTitle>
                            <CardDescription>Connected accounts</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                {setting.social_facebook && (
                                    <a
                                        href={setting.social_facebook}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 rounded-lg border bg-blue-50 p-4 transition hover:bg-blue-100"
                                    >
                                        <Facebook className="h-6 w-6 text-blue-600" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium text-blue-900 uppercase">Facebook</p>
                                            <p className="truncate text-sm text-blue-700">{setting.social_facebook}</p>
                                        </div>
                                    </a>
                                )}

                                {setting.social_instagram && (
                                    <a
                                        href={setting.social_instagram}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 rounded-lg border bg-pink-50 p-4 transition hover:bg-pink-100"
                                    >
                                        <Instagram className="h-6 w-6 text-pink-600" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium text-pink-900 uppercase">Instagram</p>
                                            <p className="truncate text-sm text-pink-700">{setting.social_instagram}</p>
                                        </div>
                                    </a>
                                )}

                                {setting.social_whatsapp && (
                                    <a
                                        href={`https://wa.me/${setting.social_whatsapp.replace(/\D/g, '')}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 rounded-lg border bg-green-50 p-4 transition hover:bg-green-100"
                                    >
                                        <MessageCircle className="h-6 w-6 text-green-600" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium text-green-900 uppercase">WhatsApp</p>
                                            <p className="truncate text-sm text-green-700">{setting.social_whatsapp}</p>
                                        </div>
                                    </a>
                                )}

                                {setting.social_telegram && (
                                    <a
                                        href={setting.social_telegram}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 rounded-lg border bg-sky-50 p-4 transition hover:bg-sky-100"
                                    >
                                        <Send className="h-6 w-6 text-sky-600" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium text-sky-900 uppercase">Telegram</p>
                                            <p className="truncate text-sm text-sky-700">{setting.social_telegram}</p>
                                        </div>
                                    </a>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Timeline */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Timeline</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="flex items-center gap-3 rounded-lg border bg-gray-50 p-3">
                                <Calendar className="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Created At</p>
                                    <p className="font-mono text-sm">{new Date(setting.created_at).toLocaleString('en-GB')}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border bg-gray-50 p-3">
                                <Calendar className="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Updated At</p>
                                    <p className="font-mono text-sm">{new Date(setting.updated_at).toLocaleString('en-GB')}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
