// resources/js/pages/auth/register.tsx

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail, User } from 'lucide-react';
import { FormEventHandler } from 'react';

interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Create an Account" description="Join ZUHA PERFUME and start your journey">
            <Head title="Sign up — ZUHA PERFUME" />

            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-5">
                    {/* Name */}
                    <div className="grid gap-2">
                        <Label htmlFor="name" className="text-gray-300">
                            Full Name
                        </Label>
                        <div className="relative">
                            <User className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/60" />
                            <Input
                                id="name"
                                type="text"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="John Doe"
                                className="border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-gray-600 focus:border-[#D4AF37] focus:ring-[#D4AF37]/30"
                            />
                        </div>
                        <InputError message={errors.name} />
                    </div>

                    {/* Email */}
                    <div className="grid gap-2">
                        <Label htmlFor="email" className="text-gray-300">
                            Email address
                        </Label>
                        <div className="relative">
                            <Mail className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/60" />
                            <Input
                                id="email"
                                type="email"
                                required
                                tabIndex={2}
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="email@example.com"
                                className="border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-gray-600 focus:border-[#D4AF37] focus:ring-[#D4AF37]/30"
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    {/* Password */}
                    <div className="grid gap-2">
                        <Label htmlFor="password" className="text-gray-300">
                            Password
                        </Label>
                        <div className="relative">
                            <Lock className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/60" />
                            <Input
                                id="password"
                                type="password"
                                required
                                tabIndex={3}
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Min. 8 characters"
                                className="border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-gray-600 focus:border-[#D4AF37] focus:ring-[#D4AF37]/30"
                            />
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    {/* Password Confirmation */}
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation" className="text-gray-300">
                            Confirm Password
                        </Label>
                        <div className="relative">
                            <Lock className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/60" />
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                tabIndex={4}
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder="Confirm password"
                                className="border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-gray-600 focus:border-[#D4AF37] focus:ring-[#D4AF37]/30"
                            />
                        </div>
                        <InputError message={errors.password_confirmation} />
                    </div>

                    {/* Submit */}
                    <Button
                        type="submit"
                        className="mt-2 w-full gap-2 border border-[#D4AF37]/40 bg-gradient-to-r from-[#D4AF37] to-[#B8860B] font-semibold text-black shadow-lg shadow-[#D4AF37]/20 transition-all hover:from-[#B8860B] hover:to-[#8B6914] hover:shadow-[#D4AF37]/40"
                        tabIndex={5}
                        disabled={processing}
                    >
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Create Account
                    </Button>
                </div>

                {/* Login link */}
                <div className="text-center text-sm text-gray-400">
                    Already have an account?{' '}
                    <TextLink href={route('login')} className="font-semibold text-[#D4AF37] hover:text-[#B8860B]" tabIndex={6}>
                        Log in
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
