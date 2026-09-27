import AppLogoIcon from '@/components/app-logo-icon';
import { Head, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthLayout({ children, title, description }: PropsWithChildren<AuthLayoutProps>) {
    const shop = (usePage().props as {
        shop?: { name?: string; slogan?: string; support_phone?: string; manufacturer?: string };
    }).shop;
    const brand = shop?.name || 'ZUHA PERFUME';
    const slogan = shop?.slogan || 'Luxury fragrance, precise retail control';
    const manufacturer = shop?.manufacturer || 'Tezhoshan';
    const supportPhone = shop?.support_phone || '0744442863';

    return (
        <div className="relative min-h-svh overflow-hidden bg-[#070707] text-white">
            <Head>
                <link
                    href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <div className="pointer-events-none absolute inset-0">
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_20%_20%,rgba(212,175,55,0.18),transparent_45%),radial-gradient(ellipse_at_80%_80%,rgba(184,134,11,0.12),transparent_40%)]" />
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(212,175,55,0.35) 1px, transparent 1px), linear-gradient(90deg, rgba(212,175,55,0.35) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                        maskImage: 'radial-gradient(ellipse at center, black 20%, transparent 75%)',
                    }}
                />
            </div>

            <div className="relative z-10 mx-auto grid min-h-svh w-full max-w-6xl lg:grid-cols-2">
                <section className="relative flex flex-col justify-between px-8 py-10 md:px-12 md:py-14">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-[#D4AF37]/35 bg-black/40 shadow-[0_0_40px_rgba(212,175,55,0.15)]">
                            <AppLogoIcon className="h-6 w-6" />
                        </div>
                        <span className="text-xs font-medium tracking-[0.35em] text-[#D4AF37]/80 uppercase">Point of sale</span>
                    </div>

                    <div className="my-12 max-w-md animate-[authRise_700ms_ease-out]">
                        <h1
                            className="font-display text-5xl leading-[0.95] font-semibold tracking-wide md:text-6xl"
                            style={{
                                background: 'linear-gradient(135deg, #F3E5AB 0%, #D4AF37 45%, #8B6914 100%)',
                                WebkitBackgroundClip: 'text',
                                WebkitTextFillColor: 'transparent',
                                backgroundClip: 'text',
                            }}
                        >
                            {brand}
                        </h1>
                        <p className="mt-5 max-w-sm text-sm leading-relaxed text-neutral-400 md:text-base">{slogan}</p>
                        <div className="mt-8 h-px w-28 bg-gradient-to-r from-[#D4AF37] to-transparent" />
                    </div>

                    <div className="space-y-1 text-xs text-neutral-600">
                        <p>© {new Date().getFullYear()} {manufacturer}</p>
                        <p>
                            Support{' '}
                            <a href={`tel:${supportPhone}`} className="text-[#D4AF37]/80 hover:text-[#D4AF37]">
                                {supportPhone}
                            </a>
                        </p>
                    </div>
                </section>

                <section className="flex items-center justify-center px-6 py-10 md:px-10">
                    <div className="w-full max-w-md animate-[authRise_700ms_ease-out] [animation-delay:80ms]">
                        <div className="rounded-3xl border border-[#D4AF37]/18 bg-[#0E0E0E]/85 p-8 shadow-[0_30px_80px_rgba(0,0,0,0.55)] backdrop-blur-xl md:p-9">
                            {(title || description) && (
                                <div className="mb-7">
                                    {title && (
                                        <h2 className="font-display text-3xl font-semibold tracking-wide text-[#E8D5A3]">
                                            {title}
                                        </h2>
                                    )}
                                    {description && (
                                        <p className="mt-2 text-sm leading-relaxed text-neutral-400">{description}</p>
                                    )}
                                </div>
                            )}
                            <div>{children}</div>
                        </div>
                    </div>
                </section>
            </div>

            <style>{`
                @keyframes authRise {
                    from { opacity: 0; transform: translateY(14px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </div>
    );
}
