import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface SplashProps {
    app: {
        version: string;
        name?: string;
    };
}

export default function Splash({ app }: SplashProps) {
    const [showEnter, setShowEnter] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => setShowEnter(true), 600);
        return () => clearTimeout(timer);
    }, []);

    return (
        <>
            <Head title="ZUHA Management System" />

            <div
                className="relative min-h-screen bg-cover bg-center bg-no-repeat"
                style={{
                    backgroundImage: "url('/images/zuha-splash.svg')",
                }}
            >
                {/* Dark Overlay */}
                <div className="absolute inset-0 bg-black/55"></div>

                {/* Content */}
                <div className="relative z-10 flex min-h-screen flex-col items-center justify-center px-6 text-center">
                    {/* Logo */}
                    <img loading="lazy" decoding="async" src="/images/logo.png" alt="ZUHA Logo" className="mb-8 h-28 w-28 object-contain" />

                    {/* Title */}
                    <h1 className="mb-4 text-5xl font-bold tracking-[8px] text-[#D4AF37] md:text-7xl">ZUHA</h1>

                    {/* System Name */}
                    <h2 className="mb-3 text-xl font-light tracking-[6px] text-white md:text-2xl">MANAGEMENT SYSTEM</h2>

                    {/* Welcome */}
                    <p className="mb-12 text-sm tracking-[10px] text-gray-300 uppercase">Welcome</p>

                    {/* Enter Button — بدون Ziggy */}
                    <div className={`transition-all duration-700 ${showEnter ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}>
                        <Link
                            href="/dashboard"
                            className="rounded-full border border-[#D4AF37] bg-[#D4AF37] px-10 py-4 text-sm font-semibold tracking-widest text-black transition-all duration-300 hover:scale-105 hover:bg-[#e6c14c]"
                        >
                            ENTER SYSTEM
                        </Link>
                    </div>

                    {/* Version */}
                    <p className="absolute bottom-8 text-xs tracking-wider text-gray-400">Version {app.version}</p>
                </div>
            </div>
        </>
    );
}
