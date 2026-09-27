import { BackButton } from '@/components/form-page';
// resources/js/pages/app-settings/create.tsx

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

interface Props {
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'App Settings', href: '/app-settings' },
    { title: 'Create', href: '/app-settings/create' },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function AppSettingsCreate({ errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        name: '',
        company_name: '',
        company_name_local: '',
        phone: '',
        phone_2: '',
        email: '',
        website: '',
        address: '',
        social_facebook: '',
        social_instagram: '',
        social_whatsapp: '',
        social_telegram: '',
    });

    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

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

        Object.entries(form).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                formData.append(key, String(value));
            }
        });

        if (logoFile) formData.append('logo', logoFile);

        router.post('/app-settings', formData, {
            forceFormData: true,
            onSuccess: () => {
                toast({ title: 'Success', description: 'Settings created successfully.' });
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Setting" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/app-settings" label="Back" />
                    <div className="flex items-center gap-3">
                        <SettingsIcon className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Setting</h1>
                    </div>
                </div>

                {/* Warning */}
                <div className="rounded-lg border-2 border-blue-200 bg-blue-50 p-4">
                    <div className="flex items-start gap-3">
                        <SettingsIcon className="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
                        <div className="text-sm">
                            <p className="font-semibold text-blue-900">Important</p>
                            <p className="mt-0.5 text-blue-700">You can only create one settings record. After that, only editing is allowed.</p>
                        </div>
                    </div>
                </div>

                {/* ═══════════ Company Info ═══════════ */}
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
                                    placeholder="e.g. ZUHA PERFUME"
                                    className={errors.name ? 'border-destructive' : ''}
                                />
                                {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Company Name (Official)</Label>
                                <Input
                                    value={form.company_name}
                                    onChange={(e) => handleChange('company_name', e.target.value)}
                                    placeholder="e.g. Zuha Perfume Trading Co."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Company Name (Local)</Label>
                                <Input
                                    value={form.company_name_local}
                                    onChange={(e) => handleChange('company_name_local', e.target.value)}
                                    placeholder="e.g. Zuha Perfume"
                                />
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
                                {errors.logo && <p className="text-destructive text-sm">{errors.logo}</p>}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Contact ═══════════ */}
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
                                <Input value={form.phone} onChange={(e) => handleChange('phone', e.target.value)} placeholder="+93 700 000 000" />
                            </div>

                            <div className="space-y-2">
                                <Label>Secondary Phone</Label>
                                <Input value={form.phone_2} onChange={(e) => handleChange('phone_2', e.target.value)} placeholder="+93 700 000 000" />
                            </div>

                            <div className="space-y-2">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) => handleChange('email', e.target.value)}
                                    placeholder="info@example.com"
                                    className={errors.email ? 'border-destructive' : ''}
                                />
                                {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Website</Label>
                                <Input
                                    value={form.website}
                                    onChange={(e) => handleChange('website', e.target.value)}
                                    placeholder="https://example.com"
                                    className={errors.website ? 'border-destructive' : ''}
                                />
                                {errors.website && <p className="text-destructive text-sm">{errors.website}</p>}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Address ═══════════ */}
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
                            <Textarea
                                value={form.address}
                                onChange={(e) => handleChange('address', e.target.value)}
                                rows={3}
                                placeholder="Street, City, Country..."
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Social Media ═══════════ */}
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
                                <Input
                                    value={form.social_facebook}
                                    onChange={(e) => handleChange('social_facebook', e.target.value)}
                                    placeholder="https://facebook.com/..."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <Instagram className="h-4 w-4 text-pink-600" />
                                    Instagram
                                </Label>
                                <Input
                                    value={form.social_instagram}
                                    onChange={(e) => handleChange('social_instagram', e.target.value)}
                                    placeholder="https://instagram.com/..."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <MessageCircle className="h-4 w-4 text-green-600" />
                                    WhatsApp
                                </Label>
                                <Input
                                    value={form.social_whatsapp}
                                    onChange={(e) => handleChange('social_whatsapp', e.target.value)}
                                    placeholder="+93 700 000 000"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    <Send className="h-4 w-4 text-sky-600" />
                                    Telegram
                                </Label>
                                <Input
                                    value={form.social_telegram}
                                    onChange={(e) => handleChange('social_telegram', e.target.value)}
                                    placeholder="https://t.me/..."
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex gap-2 border-t pt-4">
                    <Button type="submit" disabled={loading} className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Saving...' : 'Save Setting'}
                    </Button>
                    <BackButton href="/app-settings" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
