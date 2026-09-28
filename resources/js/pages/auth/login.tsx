import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, Lock, Mail, ShieldAlert, ShieldCheck } from 'lucide-react';
import { FormEventHandler, useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    lockoutSeconds?: number | null;
}

const fieldClass =
    'h-11 border-[#D4AF37]/20 bg-[#0A0A0A] pl-10 text-white placeholder:text-neutral-600 focus-visible:border-[#D4AF37] focus-visible:ring-[#D4AF37]/25';

function formatCountdown(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

export default function Login({ status, canResetPassword, lockoutSeconds = null }: LoginProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);
    const emailRef = useRef<HTMLInputElement>(null);
    const passwordRef = useRef<HTMLInputElement>(null);
    const [lockSecondsLeft, setLockSecondsLeft] = useState(() => (lockoutSeconds && lockoutSeconds > 0 ? lockoutSeconds : 0));

    const clearCredentialFields = useCallback(() => {
        [emailRef.current, passwordRef.current].forEach((field) => {
            if (field) field.value = '';
        });
        setData('email', '');
        setData('password', '');
    }, [setData]);

    useEffect(() => {
        clearCredentialFields();

        const frame = window.requestAnimationFrame(clearCredentialFields);
        const timer = window.setTimeout(clearCredentialFields, 400);

        return () => {
            window.cancelAnimationFrame(frame);
            window.clearTimeout(timer);
        };
    }, [clearCredentialFields]);

    useEffect(() => {
        const onRestore = (event: PageTransitionEvent) => {
            if (event.persisted) clearCredentialFields();
        };

        window.addEventListener('pageshow', onRestore);

        return () => window.removeEventListener('pageshow', onRestore);
    }, [clearCredentialFields]);

    const serverLockSeconds = useMemo(() => {
        const raw = (errors as Record<string, string>).lockout;
        const fromError = raw ? parseInt(raw, 10) : 0;
        const fromProp = lockoutSeconds && lockoutSeconds > 0 ? lockoutSeconds : 0;
        const parsed = Number.isFinite(fromError) && fromError > 0 ? fromError : fromProp;
        return parsed > 0 ? parsed : 0;
    }, [errors, lockoutSeconds]);

    useEffect(() => {
        if (serverLockSeconds > 0) {
            setLockSecondsLeft(serverLockSeconds);
        }
    }, [serverLockSeconds]);

    useEffect(() => {
        if (lockSecondsLeft <= 0) {
            return;
        }

        const timer = window.setInterval(() => {
            setLockSecondsLeft((prev) => (prev <= 1 ? 0 : prev - 1));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [lockSecondsLeft > 0]); // eslint-disable-line react-hooks/exhaustive-deps -- only restart when lock starts/ends

    const isLocked = lockSecondsLeft > 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isLocked || processing) return;

        clearErrors();
        post(route('login'), {
            onSuccess: () => {
                reset('password');
                setShowPassword(false);
                setLockSecondsLeft(0);
            },
        });
    };

    return (
        <AuthLayout title="Sign in" description="Enter your email/username and password to open the ZUHA workspace.">
            <Head title="Log in — ZUHA PERFUME" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2.5 text-center text-sm text-emerald-300">
                    {status}
                </div>
            )}

            {isLocked && (
                <div className="mb-5 flex items-start gap-3 rounded-xl border border-red-500/40 bg-red-500/10 px-3 py-3 text-sm text-red-200">
                    <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p className="font-semibold">Sign-in locked</p>
                        <p className="text-red-200/90">Too many failed attempts. Wait {formatCountdown(lockSecondsLeft)} before trying again.</p>
                    </div>
                </div>
            )}

            {!isLocked && errors.credentials && (
                <div className="mb-5 flex items-start gap-3 rounded-xl border border-red-500/40 bg-red-500/10 px-3 py-3 text-sm text-red-200">
                    <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p className="font-semibold">Sign-in problem</p>
                        <p className="text-red-200/90">{errors.credentials}</p>
                    </div>
                </div>
            )}

            <form className="flex flex-col gap-5" onSubmit={submit} autoComplete="off">
                <div className="grid gap-2">
                    <Label htmlFor="email" className="text-neutral-300">
                        Email or username
                    </Label>
                    <div className="relative">
                        <Mail className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="email"
                            ref={emailRef}
                            name="zuha-login-identifier"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="off"
                            data-form-type="other"
                            data-1p-ignore
                            data-lpignore="true"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com or username"
                            className={fieldClass}
                            disabled={isLocked || processing}
                        />
                    </div>
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password" className="text-neutral-300">
                            Password
                        </Label>
                        {canResetPassword && (
                            <TextLink href={route('password.request')} className="ml-auto text-sm text-[#D4AF37] hover:text-[#F0D78C]" tabIndex={5}>
                                Forgot password?
                            </TextLink>
                        )}
                    </div>
                    <div className="relative">
                        <Lock className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#D4AF37]/70" />
                        <Input
                            id="password"
                            ref={passwordRef}
                            name="zuha-login-secret"
                            type={showPassword ? 'text' : 'password'}
                            required
                            tabIndex={2}
                            autoComplete="new-password"
                            data-form-type="other"
                            data-1p-ignore
                            data-lpignore="true"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            className={`${fieldClass} pr-10`}
                            disabled={isLocked || processing}
                        />
                        <button
                            type="button"
                            tabIndex={-1}
                            onClick={() => setShowPassword((v) => !v)}
                            className="absolute top-1/2 right-3 -translate-y-1/2 text-neutral-500 transition hover:text-[#D4AF37] disabled:opacity-40"
                            title={showPassword ? 'Hide password' : 'Show password'}
                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                            disabled={isLocked}
                        >
                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                    </div>
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
                            disabled={isLocked || processing}
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
                    className="mt-1 h-11 w-full gap-2 bg-gradient-to-r from-[#D4AF37] to-[#B8860B] font-semibold text-black hover:from-[#E0BD4A] hover:to-[#C9961A] disabled:opacity-60"
                    tabIndex={4}
                    disabled={processing || isLocked}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isLocked ? `Locked · ${formatCountdown(lockSecondsLeft)}` : 'Continue'}
                </Button>
            </form>
        </AuthLayout>
    );
}
