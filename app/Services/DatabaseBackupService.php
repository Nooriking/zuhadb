<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * @return list<array{name: string, path: string, size: int, size_label: string, created_at: string}>
     */
    public function listBackups(): array
    {
        return collect(File::files($this->directory()))
            ->filter(fn ($file) => str_ends_with(strtolower($file->getFilename()), '.sql'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values()
            ->map(function ($file) {
                $size = $file->getSize();

                return [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $size,
                    'size_label' => $this->formatBytes($size),
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            })
            ->all();
    }

    public function createBackup(?int $keep = 14): string
    {
        $config = $this->mysqlConfig();
        $filename = sprintf(
            '%s_%s.sql',
            $config['database'] ?? 'database',
            now()->format('Y-m-d_His')
        );
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        $dump = $this->dumpWithMysqldump($config) ?? $this->dumpWithPhp((string) ($config['database'] ?? ''));

        if ($dump === null || $dump === '') {
            throw new RuntimeException('Database backup failed.');
        }

        File::put($path, $dump);
        $this->pruneOldBackups(max(1, $keep ?? 14));

        return $path;
    }

    public function absolutePath(string $filename): string
    {
        $safe = basename($filename);
        if (! str_ends_with(strtolower($safe), '.sql') || str_contains($safe, '..')) {
            throw new RuntimeException('Invalid backup file name.');
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$safe;
        if (! is_file($path)) {
            throw new RuntimeException('Backup file not found.');
        }

        return $path;
    }

    public function storeUploadedBackup(UploadedFile $file): string
    {
        $original = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($original, PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            throw new RuntimeException('Only .sql backup files are allowed.');
        }

        $safeName = sprintf(
            'upload_%s_%s.sql',
            now()->format('Y-m-d_His'),
            preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($original, PATHINFO_FILENAME)) ?: 'backup'
        );

        $file->move($this->directory(), $safeName);

        return $this->directory().DIRECTORY_SEPARATOR.$safeName;
    }

    public function restoreFromPath(string $path): void
    {
        if (! is_file($path) || ! str_ends_with(strtolower($path), '.sql')) {
            throw new RuntimeException('Backup file is missing or invalid.');
        }

        $config = $this->mysqlConfig();
        $restored = $this->restoreWithMysql($config, $path);

        if (! $restored) {
            $this->restoreWithPhp($path);
        }
    }

    public function deleteBackup(string $filename): void
    {
        $path = $this->absolutePath($filename);
        File::delete($path);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mysqlConfig(): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Backup currently supports MySQL only.');
        }

        return $config;
    }

    protected function pruneOldBackups(int $keep): void
    {
        $files = collect(File::files($this->directory()))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        foreach ($files->slice($keep) as $old) {
            File::delete($old->getPathname());
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function dumpWithMysqldump(array $config): ?string
    {
        $binary = $this->resolveBinary(['mysqldump', 'mysqldump.exe']);
        if (! $binary) {
            return null;
        }

        $args = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            $config['database'],
        ];

        $process = new Process($args);
        $process->setTimeout(600);
        if (filled($config['password'] ?? null)) {
            $process->setEnv(['MYSQL_PWD' => (string) $config['password']]);
        }

        $process->run();
        if (! $process->isSuccessful()) {
            report(new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput())));

            return null;
        }

        return $process->getOutput();
    }

    protected function dumpWithPhp(string $database): string
    {
        $sql = "-- Perfume POS database backup\n-- Database: {$database}\n-- Created: ".now()->toDateTimeString()."\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_'.$database;

        foreach ($tables as $tableRow) {
            $table = $tableRow->{$key} ?? null;
            if (! $table) {
                continue;
            }

            $create = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $createSql = $create->{'Create Table'} ?? null;
            if ($createSql) {
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n";
            }

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $table) {
                foreach ($rows as $row) {
                    $values = collect((array) $row)->map(function ($value) {
                        if ($value === null) {
                            return 'NULL';
                        }

                        return "'".str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value)."'";
                    })->implode(', ');

                    $columns = collect(array_keys((array) $row))
                        ->map(fn ($col) => '`'.$col.'`')
                        ->implode(', ');

                    $sql .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
                }
            });

            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function restoreWithMysql(array $config, string $path): bool
    {
        $binary = $this->resolveBinary(['mysql', 'mysql.exe']);
        if (! $binary) {
            return false;
        }

        $args = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
            $config['database'],
        ];

        $process = new Process($args);
        $process->setTimeout(900);
        $process->setInput(File::get($path));
        if (filled($config['password'] ?? null)) {
            $process->setEnv(['MYSQL_PWD' => (string) $config['password']]);
        }

        $process->run();
        if (! $process->isSuccessful()) {
            report(new RuntimeException('mysql restore failed: '.trim($process->getErrorOutput())));

            return false;
        }

        return true;
    }

    protected function restoreWithPhp(string $path): void
    {
        $sql = File::get($path);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Backup file is empty.');
        }

        // Split on semicolons outside quotes (simple parser for dump files).
        $statements = $this->splitSqlStatements($sql);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    continue;
                }
                DB::unprepared($trimmed);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return list<string>
     */
    protected function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($char === '\\' && ($inSingle || $inDouble)) {
                $buffer .= $char.$next;
                $i++;
                continue;
            }

            if ($char === "'" && ! $inDouble) {
                $inSingle = ! $inSingle;
                $buffer .= $char;
                continue;
            }

            if ($char === '"' && ! $inSingle) {
                $inDouble = ! $inDouble;
                $buffer .= $char;
                continue;
            }

            if ($char === ';' && ! $inSingle && ! $inDouble) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    /**
     * @param  list<string>  $names
     */
    protected function resolveBinary(array $names): ?string
    {
        $candidates = $names;

        $roots = [
            'C:\\xampp\\mysql\\bin',
            'C:\\wamp64\\bin\\mysql',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin',
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin',
        ];

        if (is_dir('C:\\laragon\\bin\\mysql')) {
            foreach (File::directories('C:\\laragon\\bin\\mysql') as $dir) {
                $roots[] = $dir.DIRECTORY_SEPARATOR.'bin';
            }
        }

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            foreach ($names as $name) {
                $candidates[] = $root.DIRECTORY_SEPARATOR.$name;
            }
        }

        foreach ($candidates as $candidate) {
            if (! str_contains($candidate, DIRECTORY_SEPARATOR) && ! str_contains($candidate, '/')) {
                $which = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', $candidate]);
                $which->run();
                if ($which->isSuccessful() && filled(trim($which->getOutput()))) {
                    return trim(explode("\n", str_replace("\r", '', $which->getOutput()))[0]);
                }

                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }
}
