// resources/js/pages/app-settings/edit.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Facebook,
    Instagram,
    MapPin,
    MessageCircle,
    Phone,
    Save,
    Send,
    Settings as SettingsIcon,
    Upload,
    X,
} from 'lucide-react';
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
    social_facebook: string | null;
    social_instagram: string | null;
    social_whatsapp: string | null;
    social_telegram: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    setting: Setting;
    errors?: Record<string, string>;
}

const breadcrumbs = (setting: Setting): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'App Settings', href: '/app-settings' },
    { title: setting.name, href: `/app-settings/${setting.id}` },
    { title: 'Edit', href: `/app-settings/${setting.id}/edit` },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function AppSettingsEdit({ setting, errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        name: setting.name || '',
        company_name: setting.company_name ?? '',
        company_name_local: setting.company_name_local ?? '',
        phone: setting.phone ?? '',
        phone_2: setting.phone_2 ?? '',
        email: setting.email ?? '',
        website: setting.website ?? '',
        address: setting.address ?? '',
        social_facebook: setting.social_facebook ?? '',
        social_instagram: setting.social_instagram ?? '',
        social_whatsapp: setting.social_whatsapp ?? '',
        social_telegram: setting.social_telegram ?? '',
    });

    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(setting.logo_url || null);

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const n = { ...prev };
                delete n[field];
                return n;
            });
        }
    };

    const handleLogoChange = (file: File | null) => {
        if (!file) return;
        setLogoFile(file);

        const reader = new FileReader();
        reader.onload = (e) => setLogoPreview(e.target?.result as string);
        reader.readAsDataURL(file);
    };

    const clearLogo = () => {
        setLogoFile(null);
        setLogoPreview(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const formData = new FormData();
        formData.append('_method', 'PUT');

        Object.entries(form).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                formData.append(key, String(value));
            }
        });

        if (logoFile) formData.append('logo', logoFile);

        router.post(`/app-settings/${setting.id}`, formData, {
            forceFormData: true,
            onSuccess: () => {
                toast({ title: 'Success', description: 'Settings updated successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);
                toast({ title: 'Error', description: 'Please check the form.', variant: 'destructive' });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(setting)}>
            <Head title={`Edit ${setting.name}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <SettingsIcon className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Setting</h1>
                        <span className="text-muted-foreground text-sm">{setting.name}</span>
                    </div>
                    <Link href={`/app-settings/${setting.id}`}>
                        <Button variant="outline" type="button" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                {/* Company Info */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Building2 className="text-primary h-5 w-5" />
                            <div>
                                <CardTitle>Company Information</CardTitle>
                                <CardDescription>Company name and logo</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>
                                    Display Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    value={form.name}
                                    onChange={(e) => handleChange('name', e.target.value)}
                                    className={errors.name ? 'border-destructive' : ''}
                                />
                                {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Company Name (Official)</Label>
                                <Input value={form.company_name} onChange={(e) => handleChange('company_name', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label>Company Name (Local)</Label>
                                <Input value={form.company_name_local} onChange={(e) => handleChange('company_name_local', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label>Logo</Label>
                                <div className="flex items-start gap-4">
                                    {logoPreview && (
                                        <div className="relative">
                                            <img loading="lazy" decoding="async" src={logoPreview} alt="Preview" className="h-20 w-20 rounded border bg-white object-contain p-1" />
                                            <button
                                                type="button"
                                                onClick={clearLogo}
                                                className="bg-destructive text-destructive-foreground absolute -top-2 -right-2 rounded-full p-0.5"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        </div>
                                    )}
                                    <label className="hover:bg-accent flex cursor-pointer items-center gap-2 rounded border border-dashed px-4 py-2">
                                        <Upload className="h-4 w-4" />
                                        <span className="text-sm">{logoPreview ? 'Change' : 'Upload'}</span>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={(e) => handleLogoChange(e.target.files?.[0] || null)}
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Contact */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Phone className="text-primary h-5 w-5" />
                            <div>
                                <CardTitle>Contact Information</CardTitle>
                                <CardDescription>Phone, email, website</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Primary Phone</Label>
                                <Input value={form.phone} onChange={(e) => handleChange('phone', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label>Secondary Phone</Label>
                                <Input value={form.phone_2} onChange={(e) => handleChange('phone_2', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) => handleChange('email', e.target.value)}
                                    className={errors.email ? 'border-destructive' : ''}
                                />
                                {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Website</Label>
                                <Input
                                    value={form.website}
                                    onChange={(e) => handleChange('website', e.target.value)}
                                    className={errors.website ? 'border-destructive' : ''}
                                />
                                {errors.website && <p className="text-destructive text-sm">{errors.website}</p>}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Address */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <MapPin className="text-primary h-5 w-5" />
                            <div>
                                <CardTitle>Address</CardTitle>
                                <CardDescription>Physical location</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <Label>Full Address</Label>
                            <Textarea value={form.address} onChange={(e) => handleChange('address', e.target.value)} rows={3} />
                        </div>
                    </CardContent>
                </Card>

                {/* Social */}
                <Card>
                    <CardHeader>
                        <CardTitle>Social Media</CardTitle>
                        <CardDescription>Optional links</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <Facebook className="h-4 w-4 text-blue-600" />
                                    Facebook
                                </Label>
                                <Input value={form.social_facebook} onChange={(e) => handleChange('social_facebook', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <Instagram className="h-4 w-4 text-pink-600" />
                                    Instagram
                                </Label>
                                <Input value={form.social_instagram} onChange={(e) => handleChange('social_instagram', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <MessageCircle className="h-4 w-4 text-green-600" />
                                    WhatsApp
                                </Label>
                                <Input value={form.social_whatsapp} onChange={(e) => handleChange('social_whatsapp', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <Send className="h-4 w-4 text-sky-600" />
                                    Telegram
                                </Label>
                                <Input value={form.social_telegram} onChange={(e) => handleChange('social_telegram', e.target.value)} />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Meta */}
                <div className="rounded-lg border bg-gray-50 p-4 text-sm">
                    <div className="grid grid-cols-2 gap-y-2">
                        <span className="text-gray-500">ID:</span>
                        <span className="text-right font-mono">{setting.id}</span>
                        <span className="text-gray-500">Created:</span>
                        <span className="text-right font-mono">{new Date(setting.created_at).toLocaleString('en-GB')}</span>
                        <span className="text-gray-500">Updated:</span>
                        <span className="text-right font-mono">{new Date(setting.updated_at).toLocaleString('en-GB')}</span>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex gap-2 border-t pt-4">
                    <Button type="submit" disabled={loading} className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Updating...' : 'Save Changes'}
                    </Button>
                    <Link href={`/app-settings/${setting.id}`}>
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
