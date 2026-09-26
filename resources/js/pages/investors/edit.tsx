// resources/js/pages/investors/edit.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string | null;
}

interface Investor {
    id: number;
    user_id: number | null;
    full_name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
}

interface Props {
    investor: Investor;
    users: User[];
    errors?: Record<string, string>;
}

const breadcrumbs = (investor: Investor): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
    { title: investor.full_name, href: `/investors/${investor.id}` },
    { title: 'Edit', href: `/investors/${investor.id}/edit` },
];

export default function InvestorsEdit({ investor, users, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        user_id: investor.user_id ? String(investor.user_id) : 'none',
        full_name: investor.full_name,
        phone: investor.phone || '',
        email: investor.email || '',
        address: investor.address || '',
        notes: investor.notes || '',
        status: investor.status,
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

        router.put(`/investors/${investor.id}`, payload, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
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
        <AppLayout breadcrumbs={breadcrumbs(investor)}>
            <Head title={`Edit ${investor.full_name}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Edit Investor</h1>
                        <span className="text-gray-500">{investor.full_name}</span>
                    </div>
                    <Link href={`/investors/${investor.id}`}>
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Back
                        </button>
                    </Link>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Investor Information</h2>
                        <p className="text-sm text-gray-500">Update the information for this investor.</p>
                    </div>
                    <div className="space-y-6 p-6">
                        <div className="rounded-lg border border-dashed p-4">
                            <h3 className="mb-3 text-sm font-semibold">Link to User Account</h3>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">User Account</label>
                                <select
                                    value={form.user_id}
                                    onChange={(e) => handleChange('user_id', e.target.value)}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="none">No linked user</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={String(u.id)}>
                                            {u.name} {u.email ? `(${u.email})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Full Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.full_name}
                                    onChange={(e) => handleChange('full_name', e.target.value)}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.full_name ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                />
                                {errors.full_name && <p className="text-sm text-red-600">{errors.full_name}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Phone</label>
                                <input
                                    type="text"
                                    value={form.phone}
                                    onChange={(e) => handleChange('phone', e.target.value)}
                                    dir="ltr"
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Email</label>
                                <input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) => handleChange('email', e.target.value)}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${errors.email ? 'border-red-500' : 'border-gray-300'}`}
                                />
                                {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Status</label>
                                <select
                                    value={form.status}
                                    onChange={(e) => handleChange('status', e.target.value as 'active' | 'inactive')}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Address</label>
                            <textarea
                                value={form.address}
                                onChange={(e) => handleChange('address', e.target.value)}
                                rows={3}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => handleChange('notes', e.target.value)}
                                rows={3}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                <div className="flex gap-2">
                    <button
                        type="submit"
                        disabled={loading}
                        className="rounded-md bg-black px-6 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        {loading ? 'Saving...' : 'Update Investor'}
                    </button>
                    <Link href={`/investors/${investor.id}`}>
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
