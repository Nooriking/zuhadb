import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

const fieldClass =
    'h-11 border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-neutral-600 focus-visible:border-[#D4AF37] focus-visible:ring-[#D4AF37]/25';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout title="Forgot password" description="We will email a 6-digit reset code to your account inbox.">
            <Head title="Forgot password" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2.5 text-center text-sm text-emerald-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="email" className="text-neutral-300">
                        Email address
                    </Label>
                    <div className="relative">
                        <Mail className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autoComplete="off"
                            value={data.email}
                            autoFocus
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com"
                            className={fieldClass}
                        />
                    </div>
                    <InputError message={errors.email} />
                </div>

                <Button
                    className="h-11 w-full gap-2 bg-gradient-to-r from-[#D4AF37] to-[#B8860B] font-semibold text-black hover:from-[#E0BD4A] hover:to-[#C9961A]"
                    disabled={processing}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Email a 6-digit code
                </Button>
            </form>

            <div className="mt-6 space-x-1 text-center text-sm text-neutral-400">
                <span>Or return to</span>
                <TextLink href={route('login')} className="text-[#D4AF37] hover:text-[#F0D78C]">
                    log in
                </TextLink>
            </div>
        </AuthLayout>
    );
}
