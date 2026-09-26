import { usePage } from '@inertiajs/react';

type Shop = {
    name?: string;
    phone?: string;
    address?: string;
    logo?: string | null;
};

export default function AppLogo() {
    const shop = (usePage().props as { shop?: Shop }).shop;
    const name = shop?.name || 'ZUHA PERFUME';
    const logo = shop?.logo || '/images/logo.png';

    return (
        <div className="ml-1 flex min-w-0 items-center gap-2">
            <img src={logo} alt={name} className="h-8 w-8 shrink-0 object-contain" />
            <span
                className="truncate leading-none font-bold tracking-wider group-data-[collapsible=icon]:hidden"
                style={{
                    background: 'linear-gradient(135deg, #D4AF37 0%, #B8860B 100%)',
                    WebkitBackgroundClip: 'text',
                    WebkitTextFillColor: 'transparent',
                    backgroundClip: 'text',
                }}
            >
                {name}
            </span>
        </div>
    );
}
