// resources/js/pages/settings/index.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Setting {
    id: number;
    key: string;
    value: string | null;
    type: 'string' | 'integer' | 'decimal' | 'boolean' | 'json' | 'text' | 'image';
    group: string;
    description: string | null;
    is_public: boolean;
    updater: { id: number; name: string } | null;
}

interface Props {
    settings: Setting[];
    groups: Record<string, string>;
    types: Record<string, string>;
    currentGroup: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Settings', href: '/settings' },
];

export default function SettingsIndex({ settings, groups, currentGroup }: Props) {
    const [form, setForm] = useState<Record<string, any>>(Object.fromEntries(settings.map((s) => [s.key, s.value ?? ''])));
    const [loading, setLoading] = useState(false);

    const changeGroup = (group: string) => {
        router.get('/settings', { group }, { preserveState: false });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        const payload = settings.filter((s) => s.type !== 'image').map((s) => ({ key: s.key, value: form[s.key] ?? '' }));

        router.put(
            '/settings',
            { settings: payload },
            {
                preserveScroll: true,
                onSuccess: () => setLoading(false),
                onError: () => setLoading(false),
            },
        );
    };

    const handleLogoUpload = (setting: Setting, file: File) => {
        const formData = new FormData();
        formData.append('image', file);

        router.post(`/settings/${setting.id}/upload-image`, formData, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Settings" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-bold tracking-tight">Settings</h1>
                </div>

                {/* Group Tabs */}
                <div className="flex flex-wrap gap-2 border-b pb-2">
                    {Object.entries(groups).map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => changeGroup(key)}
                            className={`rounded-md px-4 py-2 text-sm font-medium ${
                                currentGroup === key ? 'bg-black text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <h2 className="mb-6 text-lg font-semibold">{groups[currentGroup]}</h2>

                        <div className="space-y-6">
                            {settings.map((setting) => (
                                <div key={setting.id} className="space-y-2">
                                    <label className="text-sm font-medium">{setting.description || setting.key}</label>
                                    <p className="font-mono text-xs text-gray-400">{setting.key}</p>

                                    {/* Text Area */}
                                    {setting.type === 'text' && (
                                        <textarea
                                            value={form[setting.key] || ''}
                                            onChange={(e) => setForm({ ...form, [setting.key]: e.target.value })}
                                            rows={3}
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    )}

                                    {/* Boolean */}
                                    {setting.type === 'boolean' && (
                                        <select
                                            value={form[setting.key] === '1' || form[setting.key] === true ? '1' : '0'}
                                            onChange={(e) => setForm({ ...form, [setting.key]: e.target.value })}
                                            className="w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        >
                                            <option value="0">No / Disabled</option>
                                            <option value="1">Yes / Enabled</option>
                                        </select>
                                    )}

                                    {/* Image */}
                                    {setting.type === 'image' && (
                                        <div className="space-y-2">
                                            {setting.value && (
                                                <img
                                                    src={`/storage/${setting.value}`}
                                                    alt={setting.key}
                                                    className="h-20 w-20 rounded-md border object-contain"
                                                />
                                            )}
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={(e) => {
                                                    const f = e.target.files?.[0];
                                                    if (f) handleLogoUpload(setting, f);
                                                }}
                                                className="text-sm"
                                            />
                                        </div>
                                    )}

                                    {/* String / Integer / Decimal */}
                                    {(setting.type === 'string' || setting.type === 'integer' || setting.type === 'decimal') && (
                                        <input
                                            type={setting.type === 'integer' || setting.type === 'decimal' ? 'number' : 'text'}
                                            step={setting.type === 'decimal' ? '0.01' : undefined}
                                            value={form[setting.key] || ''}
                                            onChange={(e) => setForm({ ...form, [setting.key]: e.target.value })}
                                            className="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    )}

                                    {setting.updater && <p className="text-xs text-gray-400">Last updated by {setting.updater.name}</p>}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <button
                            type="submit"
                            disabled={loading}
                            className="rounded-md bg-black px-6 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                        >
                            {loading ? 'Saving...' : 'Save Settings'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
