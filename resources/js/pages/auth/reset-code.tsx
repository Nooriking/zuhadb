import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Shield } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

const fieldClass =
    'h-11 border-[#D4AF37]/20 bg-[#0A0A0A] text-white placeholder:text-neutral-600 focus-visible:border-[#D4AF37] focus-visible:ring-[#D4AF37]/25';

export default function ResetCode({ email, status }: { email: string; status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email,
        code: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.code.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Enter the code" description="Use the 6-digit email code, then choose a new password.">
            <Head title="Reset code" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2.5 text-center text-sm text-emerald-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="email" className="text-neutral-300">
                        Email
                    </Label>
                    <Input id="email" type="email" value={data.email} readOnly className={`${fieldClass} opacity-80`} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="code" className="text-neutral-300">
                        6-digit code
                    </Label>
                    <div className="relative">
                        <Shield className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                            placeholder="000000"
                            className={`${fieldClass} pl-10 font-mono tracking-[0.35em]`}
                            required
                        />
                    </div>
                    <InputError message={errors.code} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password" className="text-neutral-300">
                        New password
                    </Label>
                    <div className="relative">
                        <Lock className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={`${fieldClass} pl-10`}
                            required
                        />
                    </div>
                    <InputError message={errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation" className="text-neutral-300">
                        Confirm password
                    </Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        className={fieldClass}
                        required
                    />
                </div>
                <Button
                    className="h-11 w-full gap-2 bg-gradient-to-r from-[#D4AF37] to-[#B8860B] font-semibold text-black hover:from-[#E0BD4A] hover:to-[#C9961A]"
                    disabled={processing}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Save new password
                </Button>
            </form>

            <div className="mt-6 space-x-1 text-center text-sm text-neutral-400">
                <span>Need another code?</span>
                <TextLink href={route('password.request')} className="text-[#D4AF37] hover:text-[#F0D78C]">
                    Send again
                </TextLink>
            </div>
        </AuthLayout>
    );
}
