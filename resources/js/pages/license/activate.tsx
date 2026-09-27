import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Status {
    enabled: boolean;
    valid: boolean;
    machine_id: string;
    customer: string | null;
    issued_at: string | null;
    expires_at: string | null;
    error: string | null;
    support_phone?: string;
    support_email?: string;
}

interface Props {
    status: Status;
}

export default function LicenseActivate({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        license_key: '',
    });
    const [copied, setCopied] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/license');
    };

    const copyMachineId = async () => {
        try {
            await navigator.clipboard.writeText(status.machine_id);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            //
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[#0b0b0b] px-4 py-10 text-neutral-100">
            <Head title="Activate license" />

            <div className="w-full max-w-xl rounded-2xl border border-[#d4af37]/35 bg-[#141414] p-8 shadow-2xl">
                <div className="mb-6 text-center">
                    <img src="/images/apple-touch-icon.png" alt="ZUHA PERFUME" className="mx-auto mb-4 h-16 w-16 object-contain" />
                    <h1 className="text-2xl font-bold tracking-[0.18em] text-[#d4af37] uppercase">ZUHA PERFUME</h1>
                    <p className="mt-2 text-sm text-neutral-400">This installation is locked to one computer</p>
                </div>

                <div className="mb-6 rounded-lg border border-neutral-700 bg-black/40 p-4">
                    <Label className="text-neutral-400">Machine ID (send to vendor)</Label>
                    <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <code className="flex-1 break-all rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 font-mono text-xs text-[#d4af37]">
                            {status.machine_id}
                        </code>
                        <Button type="button" variant="outline" onClick={() => void copyMachineId()}>
                            {copied ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                    {status.error && <p className="mt-2 text-xs text-amber-400">{status.error}</p>}
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="license_key">License key</Label>
                        <textarea
                            id="license_key"
                            className="border-input bg-background min-h-28 w-full rounded-md border px-3 py-2 font-mono text-xs"
                            placeholder="ZUHA-...."
                            value={data.license_key}
                            onChange={(e) => setData('license_key', e.target.value)}
                        />
                        {errors.license_key && <p className="text-destructive text-sm">{errors.license_key}</p>}
                    </div>

                    <Button type="submit" disabled={processing} className="w-full bg-[#d4af37] text-black hover:bg-[#c4a030]">
                        Activate on this computer
                    </Button>
                </form>

                <div className="mt-6 space-y-1 text-center text-xs text-neutral-500">
                    <p>Copying this software to another PC will block it until a new license is issued.</p>
                    {(status.support_phone || status.support_email) && (
                        <p>
                            Support:{' '}
                            {status.support_phone && <span className="text-neutral-300">{status.support_phone}</span>}
                            {status.support_phone && status.support_email ? ' · ' : ''}
                            {status.support_email && <span className="text-neutral-300">{status.support_email}</span>}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
