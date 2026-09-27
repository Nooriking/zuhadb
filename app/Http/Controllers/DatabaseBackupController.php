<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backups,
    ) {}

    public function index(): Response
    {
        return Inertia::render('backups/index', [
            'backups' => $this->backups->listBackups(),
            'backupDir' => 'storage/app/backups',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $path = $this->backups->createBackup(
                keep: (int) config('business.backup_keep', 14)
            );

            ActivityLogger::log(
                action: 'backup_create',
                section: 'backups',
                description: 'Database backup created',
                properties: ['file' => basename($path)],
            );

            return back()->with('success', 'Backup created: '.basename($path));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Backup failed: '.$e->getMessage());
        }
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->backups->absolutePath($filename);

        ActivityLogger::log(
            action: 'backup_download',
            section: 'backups',
            description: 'Database backup downloaded',
            properties: ['file' => basename($path)],
        );

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'confirm' => ['required', 'in:RESTORE'],
            'backup_file' => ['nullable', 'file', 'max:512000'],
            'existing' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('backup_file')) {
            $ext = strtolower($request->file('backup_file')->getClientOriginalExtension());
            if ($ext !== 'sql') {
                return back()->with('error', 'Only .sql backup files are allowed.');
            }
        }

        try {
            if ($request->hasFile('backup_file')) {
                $path = $this->backups->storeUploadedBackup($request->file('backup_file'));
            } elseif (! empty($data['existing'])) {
                $path = $this->backups->absolutePath($data['existing']);
            } else {
                return back()->with('error', 'Choose an existing backup or upload a .sql file.');
            }

            $this->backups->restoreFromPath($path);

            ActivityLogger::log(
                action: 'backup_restore',
                section: 'backups',
                description: 'Database restored from backup',
                properties: ['file' => basename($path)],
            );

            Artisan::call('optimize:clear');

            return back()->with('success', 'Database restored from '.basename($path).'. Please sign in again if needed.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }
    }

    public function destroy(string $filename): RedirectResponse
    {
        try {
            $this->backups->deleteBackup($filename);

            ActivityLogger::log(
                action: 'backup_delete',
                section: 'backups',
                description: 'Database backup deleted',
                properties: ['file' => $filename],
            );

            return back()->with('success', 'Backup deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
