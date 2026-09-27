import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

const fieldClass =
    'h-11 border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-neutral-600 focus-visible:border-[#D4AF37] focus-visible:ring-[#D4AF37]/25';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Confirm password" description="This is a secure area. Confirm your password to continue.">
            <Head title="Confirm password" />

            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="password" className="text-neutral-300">
                        Password
                    </Label>
                    <div className="relative">
                        <Lock className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Password"
                            autoComplete="current-password"
                            value={data.password}
                            autoFocus
                            onChange={(e) => setData('password', e.target.value)}
                            className={fieldClass}
                        />
                    </div>
                    <InputError message={errors.password} />
                </div>

                <Button
                    className="h-11 w-full gap-2 bg-gradient-to-r from-[#D4AF37] to-[#B8860B] font-semibold text-black hover:from-[#E0BD4A] hover:to-[#C9961A]"
                    disabled={processing}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Confirm password
                </Button>
            </form>
        </AuthLayout>
    );
}
