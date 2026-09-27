import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail, ShieldCheck, Eye, EyeOff } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    googleEnabled?: boolean;
}

const fieldClass =
    'h-11 border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-neutral-600 focus-visible:border-[#D4AF37] focus-visible:ring-[#D4AF37]/25';

function GoogleIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" aria-hidden="true">
            <path
                fill="#EA4335"
                d="M12 10.2v3.6h5.1c-.2 1.2-.9 2.2-1.9 2.9l3.1 2.4c1.8-1.7 2.9-4.1 2.9-7 0-.7-.1-1.3-.2-1.9H12z"
            />
            <path
                fill="#34A853"
                d="M6.6 14.3l-.7.5-2.4 1.9C5.1 19.2 8.3 21 12 21c2.7 0 5-.9 6.7-2.4l-3.1-2.4c-.9.6-2 .9-3.6.9-2.8 0-5.1-1.9-6-4.4z"
            />
            <path
                fill="#4A90E2"
                d="M3.5 7.3C2.8 8.7 2.4 10.3 2.4 12s.4 3.3 1.1 4.7l3.1-2.4C6.2 13.5 6 12.8 6 12s.2-1.5.6-2.3L3.5 7.3z"
            />
            <path
                fill="#FBBC05"
                d="M12 5.5c1.5 0 2.8.5 3.9 1.5l2.9-2.9C16.9 2.4 14.7 1.5 12 1.5 8.3 1.5 5.1 3.3 3.5 7.3l3.1 2.4C7 7.4 9.3 5.5 12 5.5z"
            />
        </svg>
    );
}

export default function Login({ status, canResetPassword, googleEnabled = false }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Sign in" description="Enter your credentials to open the ZUHA workspace.">
            <Head title="Log in — ZUHA PERFUME" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2.5 text-center text-sm text-emerald-300">
                    {status}
                </div>
            )}

            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="email" className="text-neutral-300">
                        Email or username
                    </Label>
                    <div className="relative">
                        <Mail className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="email"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com or username"
                            className={fieldClass}
                        />
                    </div>
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password" className="text-neutral-300">
                            Password
                        </Label>
                        {canResetPassword && (
                            <TextLink
                                href={route('password.request')}
                                className="ml-auto text-sm text-[#D4AF37] hover:text-[#F0D78C]"
                                tabIndex={5}
                            >
                                Forgot password?
                            </TextLink>
                        )}
                    </div>
                    <div className="relative">
                        <Lock className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            className={`${fieldClass} pr-10`}
                        />
                        <button
                            type="button"
                            tabIndex={-1}
                            onClick={() => setShowPassword((v) => !v)}
                            className="absolute top-1/2 right-3 -translate-y-1/2 text-neutral-500 transition hover:text-[#D4AF37]"
                            title={showPassword ? 'Hide password' : 'Show password'}
                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                        >
                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                    </div>
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            tabIndex={3}
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                            className="border-[#D4AF37]/40 data-[state=checked]:border-[#D4AF37] data-[state=checked]:bg-[#D4AF37]"
                        />
                        <Label htmlFor="remember" className="text-neutral-300">
                            Remember me
                        </Label>
                    </div>
                    <div className="hidden items-center gap-1.5 text-[11px] tracking-wide text-neutral-500 uppercase sm:flex">
                        <ShieldCheck className="h-3.5 w-3.5 text-[#D4AF37]/70" />
                        2FA ready
                    </div>
                </div>

                <Button
                    type="submit"
                    className="mt-1 h-11 w-full gap-2 bg-gradient-to-r from-[#D4AF37] to-[#B8860B] font-semibold text-black hover:from-[#E0BD4A] hover:to-[#C9961A]"
                    tabIndex={4}
                    disabled={processing}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Continue
                </Button>
            </form>

            <div className="my-5 flex items-center gap-3">
                <div className="h-px flex-1 bg-[#D4AF37]/15" />
                <span className="text-xs tracking-wide text-neutral-500 uppercase">or</span>
                <div className="h-px flex-1 bg-[#D4AF37]/15" />
            </div>

            {googleEnabled ? (
                <a
                    href={route('google.redirect')}
                    className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md border border-[#D4AF37]/25 bg-[#0A0A0A] text-sm font-medium text-white transition hover:border-[#D4AF37]/50 hover:bg-[#111]"
                >
                    <GoogleIcon className="h-4 w-4" />
                    Continue with Google
                </a>
            ) : (
                <button
                    type="button"
                    disabled
                    className="inline-flex h-11 w-full cursor-not-allowed items-center justify-center gap-2 rounded-md border border-white/10 bg-[#0A0A0A] text-sm font-medium text-neutral-500"
                    title="Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env"
                >
                    <GoogleIcon className="h-4 w-4 opacity-50" />
                    Google sign-in is not available yet.
                </button>
            )}
        </AuthLayout>
    );
}
