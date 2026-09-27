import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fire, toastError, toastSuccess } from '@/lib/swal';
import { confirmForceDelete } from '@/lib/trash-swal';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { DatabaseBackup, Download, HardDriveDownload, Trash2, Upload } from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';

interface BackupRow {
    name: string;
    size: number;
    size_label: string;
    created_at: string;
}

interface Props {
    backups: BackupRow[];
    backupDir: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Backups', href: '/backups' },
];

export default function BackupsIndex({ backups, backupDir }: Props) {
    const flash = (usePage().props as { flash?: { success?: string; error?: string } }).flash;
    const fileRef = useRef<HTMLInputElement>(null);
    const [existing, setExisting] = useState('');
    const [busy, setBusy] = useState(false);

    const createBackup = () => {
        setBusy(true);
        router.post(
            '/backups',
            {},
            {
                preserveScroll: true,
                onSuccess: () => void toastSuccess('Backup ready', 'A new .sql backup was created.'),
                onError: () => void toastError('Backup failed'),
                onFinish: () => setBusy(false),
            },
        );
    };

    const downloadBackup = (name: string) => {
        window.location.href = `/backups/${encodeURIComponent(name)}/download`;
    };

    const deleteBackup = async (name: string) => {
        const result = await confirmForceDelete(name, 'This only deletes the backup file, not your live database.');
        if (!result.isConfirmed) return;

        router.delete(`/backups/${encodeURIComponent(name)}`, {
            preserveScroll: true,
            onSuccess: () => void toastSuccess('Deleted', 'Backup file removed.'),
            onError: () => void toastError('Could not delete backup'),
        });
    };

    const restore = async (e: FormEvent) => {
        e.preventDefault();

        const hasFile = Boolean(fileRef.current?.files?.length);
        if (!hasFile && !existing) {
            void toastError('Select a backup', 'Upload a .sql file or choose an existing backup.');
            return;
        }

        const confirm = await fire({
            icon: 'error',
            title: 'Restore database?',
            html: '<p>This <strong>replaces the live database</strong> with the backup.<br/>Type <strong>RESTORE</strong> to continue.</p>',
            input: 'text',
            inputPlaceholder: 'Type RESTORE',
            showCancelButton: true,
            confirmButtonText: 'Restore now',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            focusCancel: true,
            inputValidator: (value) => (value === 'RESTORE' ? null : 'Type RESTORE in all caps'),
        });

        if (!confirm.isConfirmed) return;

        const form = new FormData();
        form.append('confirm', 'RESTORE');
        if (hasFile && fileRef.current?.files?.[0]) {
            form.append('backup_file', fileRef.current.files[0]);
        }
        if (existing) {
            form.append('existing', existing);
        }

        setBusy(true);
        router.post('/backups/restore', form, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                void toastSuccess('Restored', 'Database restore finished.');
                if (fileRef.current) fileRef.current.value = '';
                setExisting('');
            },
            onError: () => void toastError('Restore failed'),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Database Backups" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <DatabaseBackup className="text-primary h-6 w-6" />
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Database Backups</h1>
                            <p className="text-muted-foreground text-sm">
                                Full MySQL dump (.sql). Folder: <code>{backupDir}</code>
                            </p>
                        </div>
                    </div>
                    <Button onClick={createBackup} disabled={busy} className="gap-2">
                        <HardDriveDownload className="h-4 w-4" />
                        Create backup now
                    </Button>
                </div>

                {(flash?.success || flash?.error) && (
                    <div
                        className={`rounded-md border px-4 py-3 text-sm ${
                            flash.error ? 'border-destructive/40 bg-destructive/10 text-destructive' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700'
                        }`}
                    >
                        {flash.error || flash.success}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Upload className="h-4 w-4" /> Restore from file
                            </CardTitle>
                            <CardDescription>
                                Upload a previously downloaded <strong>.sql</strong> backup, or pick one from the list.
                                This overwrites the current database.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-4" onSubmit={restore}>
                                <div className="space-y-2">
                                    <Label htmlFor="backup_file">Upload .sql backup</Label>
                                    <Input id="backup_file" ref={fileRef} type="file" accept=".sql,text/plain" />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="existing">Or use existing backup</Label>
                                    <select
                                        id="existing"
                                        className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                        value={existing}
                                        onChange={(e) => setExisting(e.target.value)}
                                    >
                                        <option value="">— Select —</option>
                                        {backups.map((b) => (
                                            <option key={b.name} value={b.name}>
                                                {b.name} ({b.size_label})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button type="submit" variant="destructive" disabled={busy} className="gap-2">
                                    <DatabaseBackup className="h-4 w-4" />
                                    Restore database
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">How it works</CardTitle>
                            <CardDescription>Keep copies offline too (USB / Google Drive).</CardDescription>
                        </CardHeader>
                        <CardContent className="text-muted-foreground space-y-2 text-sm">
                            <p>1. Click <strong>Create backup now</strong> — full DB dump is saved.</p>
                            <p>2. Click <strong>Download</strong> and store the .sql file safely.</p>
                            <p>3. Later: upload that .sql here (or select a server copy) and restore.</p>
                            <p>Nightly auto-backup also runs via scheduler: <code>php artisan app:backup-database</code></p>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>File</TableHead>
                                <TableHead>Size</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {backups.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-muted-foreground h-24 text-center">
                                        No backups yet. Create one above.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                backups.map((backup) => (
                                    <TableRow key={backup.name}>
                                        <TableCell className="font-mono text-xs">{backup.name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{backup.size_label}</Badge>
                                        </TableCell>
                                        <TableCell className="text-sm">{backup.created_at}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Download"
                                                    onClick={() => downloadBackup(backup.name)}
                                                >
                                                    <Download className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete file"
                                                    className="text-destructive"
                                                    onClick={() => void deleteBackup(backup.name)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
