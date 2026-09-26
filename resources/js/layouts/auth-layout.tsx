// resources/js/layouts/auth-layout.tsx

import AppLogoIcon from '@/components/app-logo-icon';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthLayout({ children, title, description }: PropsWithChildren<AuthLayoutProps>) {
    const shop = (usePage().props as { shop?: { name?: string } }).shop;
    const brand = shop?.name || 'ZUHA PERFUME';

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-[#0A0A0A] p-6 md:p-10">
            {/* Background Gold Glow */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-[#D4AF37] opacity-10 blur-3xl" />
                <div className="absolute -right-40 -bottom-40 h-96 w-96 rounded-full bg-[#B8860B] opacity-10 blur-3xl" />
            </div>

            <div className="relative z-10 w-full max-w-md">
                {/* Brand Header */}
                <div className="mb-8 flex flex-col items-center gap-3">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-[#D4AF37]/30 bg-gradient-to-br from-[#1A1A1A] to-[#0A0A0A] shadow-lg shadow-[#D4AF37]/10">
                        <AppLogoIcon className="h-9 w-9" />
                    </div>
                    <h1
                        className="text-3xl font-bold tracking-[0.2em]"
                        style={{
                            background: 'linear-gradient(135deg, #D4AF37 0%, #B8860B 50%, #8B6914 100%)',
                            WebkitBackgroundClip: 'text',
                            WebkitTextFillColor: 'transparent',
                            backgroundClip: 'text',
                        }}
                    >
                        {brand}
                    </h1>
                    <div className="h-[1px] w-32 bg-gradient-to-r from-transparent via-[#D4AF37] to-transparent" />
                </div>

                {/* Card */}
                <div className="rounded-2xl border border-[#D4AF37]/20 bg-[#111111]/80 p-8 shadow-2xl backdrop-blur-xl">
                    {/* Title */}
                    {(title || description) && (
                        <div className="mb-6 text-center">
                            {title && <h2 className="text-xl font-semibold text-[#D4AF37]">{title}</h2>}
                            {description && <p className="mt-2 text-sm text-gray-400">{description}</p>}
                        </div>
                    )}

                    {/* Form */}
                    <div className="text-white">{children}</div>
                </div>

                {/* Footer */}
                <p className="mt-6 text-center text-xs text-gray-500">© {new Date().getFullYear()} {brand} — All rights reserved</p>
            </div>
        </div>
    );
}
