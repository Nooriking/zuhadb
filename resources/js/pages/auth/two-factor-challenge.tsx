import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, LoaderCircle, Shield } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const fieldClass =
    'h-11 border-[#D4AF37]/20 bg-[#0A0A0A] text-white placeholder:text-neutral-600 focus-visible:border-[#D4AF37] focus-visible:ring-[#D4AF37]/25';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.login.store'));
    };

    return (
        <AuthLayout
            title="Two-factor check"
            description={
                useRecovery
                    ? 'Enter one of your recovery codes to finish signing in.'
                    : 'Open your authenticator app and enter the 6-digit code.'
            }
        >
            <Head title="Two-factor — ZUHA PERFUME" />

            <form className="flex flex-col gap-5" onSubmit={submit}>
                {!useRecovery ? (
                    <div className="grid gap-2">
                        <Label htmlFor="code" className="text-neutral-300">
                            Authentication code
                        </Label>
                        <div className="relative">
                            <Shield className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                            <Input
                                id="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                autoFocus
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                placeholder="000000"
                                className={`${fieldClass} pl-10 font-mono tracking-[0.35em]`}
                                required
                            />
                        </div>
                        <InputError message={errors.code} />
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="recovery_code" className="text-neutral-300">
                            Recovery code
                        </Label>
                        <div className="relative">
                            <KeyRound className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                            <Input
                                id="recovery_code"
                                autoFocus
                                value={data.recovery_code}
                                onChange={(e) => setData('recovery_code', e.target.value)}
                                placeholder="xxxx-xxxx-xxxx"
                                className={`${fieldClass} pl-10 font-mono`}
                                required
                            />
                        </div>
                        <InputError message={errors.recovery_code} />
                    </div>
                )}

                <Button
                    type="submit"
                    className="h-11 w-full gap-2 bg-gradient-to-r from-[#D4AF37] to-[#B8860B] font-semibold text-black hover:from-[#E0BD4A] hover:to-[#C9961A]"
                    disabled={processing}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Verify and continue
                </Button>
            </form>

            <div className="mt-6 space-y-3 text-center text-sm text-neutral-400">
                <button
                    type="button"
                    className="text-[#D4AF37] hover:text-[#F0D78C]"
                    onClick={() => {
                        setUseRecovery((v) => !v);
                        setData('code', '');
                        setData('recovery_code', '');
                    }}
                >
                    {useRecovery ? 'Use authenticator code instead' : 'Use a recovery code'}
                </button>
                <div>
                    <TextLink href={route('login')} className="text-neutral-400 hover:text-[#D4AF37]">
                        Back to login
                    </TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
