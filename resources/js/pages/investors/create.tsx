import { BackButton } from '@/components/form-page';
// resources/js/pages/investors/create.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string | null;
}

interface Props {
    users: User[];
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
    { title: 'Create Investor', href: '/investors/create' },
];

export default function InvestorsCreate({ users, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        user_id: 'none',
        full_name: '',
        phone: '',
        email: '',
        address: '',
        notes: '',
        status: 'active' as 'active' | 'inactive',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            ...form,
            user_id: form.user_id === 'none' ? '' : form.user_id,
        };

        router.post('/investors', payload, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Investor created successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);
                toast({ title: 'Error', description: 'Please check the form fields.', variant: 'destructive' });
            },
        });
    };

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Investor" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/investors" label="Back" />
                    <div className="flex items-center gap-3">
                        <UserPlus className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Investor</h1>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Investor Information</CardTitle>
                        <CardDescription>Basic info about the investor.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Link to User */}
                        <div className="rounded-lg border border-dashed p-4">
                            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                                <Users className="h-4 w-4" /> Link to User Account (optional)
                            </h3>
                            <div className="space-y-2">
                                <Label>User Account</Label>
                                <Select value={form.user_id} onValueChange={(v) => handleChange('user_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="No linked user" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No linked user</SelectItem>
                                        {users.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name}
                                                {u.email ? ` (${u.email})` : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    Only users with the "Investor" role are shown. This lets the investor log in and view their own data.
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>
                                    Full Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    type="text"
                                    value={form.full_name}
                                    onChange={(e) => handleChange('full_name', e.target.value)}
                                    placeholder="e.g. Ahmad Karimi"
                                    className={errors.full_name ? 'border-destructive' : ''}
                                />
                                {errors.full_name && <p className="text-destructive text-sm">{errors.full_name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Phone</Label>
                                <Input
                                    type="text"
                                    value={form.phone}
                                    onChange={(e) => handleChange('phone', e.target.value)}
                                    placeholder="+93 70 123 4567"
                                    dir="ltr"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) => handleChange('email', e.target.value)}
                                    placeholder="investor@example.com"
                                    className={errors.email ? 'border-destructive' : ''}
                                />
                                {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select value={form.status} onValueChange={(v) => handleChange('status', v)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Address</Label>
                            <Textarea
                                value={form.address}
                                onChange={(e) => handleChange('address', e.target.value)}
                                rows={3}
                                placeholder="Full address..."
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Notes</Label>
                            <Textarea
                                value={form.notes}
                                onChange={(e) => handleChange('notes', e.target.value)}
                                rows={3}
                                placeholder="Any extra notes..."
                            />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={loading} className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Saving...' : 'Save Investor'}
                    </Button>
                    <BackButton href="/investors" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
