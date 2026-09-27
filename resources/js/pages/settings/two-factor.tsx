import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-factor authentication',
        href: '/settings/two-factor',
    },
];

interface TwoFactorPageProps {
    enabled: boolean;
    pending: boolean;
    qrSvg: string | null;
    setupSecret: string | null;
    recoveryCodes: string[];
}

export default function TwoFactor({ enabled, pending, qrSvg, setupSecret, recoveryCodes }: TwoFactorPageProps) {
    const flashSuccess = (usePage().props as { flash?: { success?: string } }).flash?.success;

    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const regenerateForm = useForm({});
    const disableForm = useForm({ password: '', code: '' });

    const enable: FormEventHandler = (e) => {
        e.preventDefault();
        enableForm.post(route('two-factor.enable'));
    };

    const confirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirmForm.post(route('two-factor.confirm'), {
            onSuccess: () => confirmForm.reset('code'),
        });
    };

    const regenerate: FormEventHandler = (e) => {
        e.preventDefault();
        regenerateForm.post(route('two-factor.recovery-codes'));
    };

    const disable: FormEventHandler = (e) => {
        e.preventDefault();
        disableForm.delete(route('two-factor.disable'), {
            onSuccess: () => disableForm.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-factor authentication" />

            <SettingsLayout>
                <div className="space-y-8">
                    <HeadingSmall
                        title="Two-factor authentication"
                        description="Protect your account with an authenticator app (Google Authenticator, Authy, etc.)."
                    />

                    {flashSuccess && (
                        <div className="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-300">
                            {flashSuccess}
                        </div>
                    )}

                    <div className="rounded-lg border p-4 text-sm">
                        Status:{' '}
                        <span className="font-semibold">
                            {enabled ? 'Enabled' : pending ? 'Pending confirmation' : 'Disabled'}
                        </span>
                    </div>

                    {!enabled && !pending && (
                        <form onSubmit={enable} className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                When enabled, you will enter a 6-digit code after your password at login.
                            </p>
                            <Button disabled={enableForm.processing}>Enable two-factor authentication</Button>
                        </form>
                    )}

                    {pending && (
                        <div className="space-y-6">
                            <div className="space-y-3">
                                <p className="text-sm">1. Scan this QR code with your authenticator app:</p>
                                {qrSvg && (
                                    <div
                                        className="inline-block rounded-lg border bg-white p-3"
                                        dangerouslySetInnerHTML={{ __html: qrSvg }}
                                    />
                                )}
                                {setupSecret && (
                                    <p className="text-muted-foreground text-xs break-all">
                                        Or enter this key manually: <span className="font-mono text-foreground">{setupSecret}</span>
                                    </p>
                                )}
                            </div>

                            <form onSubmit={confirm} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="confirm_code">2. Enter the 6-digit code to confirm</Label>
                                    <Input
                                        id="confirm_code"
                                        inputMode="numeric"
                                        value={confirmForm.data.code}
                                        onChange={(e) =>
                                            confirmForm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))
                                        }
                                        className="max-w-xs font-mono tracking-[0.3em]"
                                        required
                                    />
                                    <InputError message={confirmForm.errors.code} />
                                </div>
                                <Button disabled={confirmForm.processing}>Confirm and enable</Button>
                            </form>
                        </div>
                    )}

                    {enabled && (
                        <div className="space-y-8">
                            {recoveryCodes.length > 0 && (
                                <div className="space-y-3">
                                    <HeadingSmall
                                        title="Recovery codes"
                                        description="Store these codes somewhere safe. Each code can be used once if you lose your phone."
                                    />
                                    <ul className="grid gap-2 rounded-lg border bg-muted/40 p-4 font-mono text-sm sm:grid-cols-2">
                                        {recoveryCodes.map((code) => (
                                            <li key={code}>{code}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <form onSubmit={regenerate}>
                                <Button type="submit" variant="secondary" disabled={regenerateForm.processing}>
                                    Generate new recovery codes
                                </Button>
                            </form>

                            <form onSubmit={disable} className="space-y-4 border-t pt-6">
                                <HeadingSmall
                                    title="Disable two-factor"
                                    description="Confirm with your password and a current authenticator code."
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="disable_password">Password</Label>
                                    <Input
                                        id="disable_password"
                                        type="password"
                                        value={disableForm.data.password}
                                        onChange={(e) => disableForm.setData('password', e.target.value)}
                                        required
                                    />
                                    <InputError message={disableForm.errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="disable_code">Authenticator code</Label>
                                    <Input
                                        id="disable_code"
                                        inputMode="numeric"
                                        value={disableForm.data.code}
                                        onChange={(e) =>
                                            disableForm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))
                                        }
                                        className="max-w-xs font-mono tracking-[0.3em]"
                                        required
                                    />
                                    <InputError message={disableForm.errors.code} />
                                </div>
                                <Button variant="destructive" disabled={disableForm.processing}>
                                    Disable two-factor authentication
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
