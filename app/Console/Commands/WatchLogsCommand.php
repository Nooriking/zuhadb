<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WatchLogsCommand extends Command
{
    protected $signature = 'logs:watch
                            {--timeout=0 : Unused; kept for compatibility with Pail}';

    protected $description = 'Stream Laravel logs. Uses Pail when pcntl is available; otherwise tails laravel.log.';

    public function handle(): int
    {
        if ($this->supportsPail()) {
            return $this->call('pail', [
                '--timeout' => (int) $this->option('timeout'),
            ]);
        }

        $path = storage_path('logs/laravel.log');

        $this->warn('Pail requires the pcntl PHP extension, which is not available on this PHP build.');
        $this->info("Tailing {$path} instead. Server, queue, and Vite are not affected.");
        $this->newLine();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (! is_file($path)) {
            touch($path);
            $this->line('Log file created. Waiting for new log entries…');
        }

        $position = filesize($path) ?: 0;

        while (true) {
            clearstatcache(true, $path);

            if (! is_file($path)) {
                $position = 0;
                usleep(400000);

                continue;
            }

            $size = filesize($path) ?: 0;

            if ($size < $position) {
                $position = 0;
            }

            if ($size > $position) {
                $handle = fopen($path, 'rb');
                if ($handle === false) {
                    usleep(400000);

                    continue;
                }

                fseek($handle, $position);
                $chunk = stream_get_contents($handle);
                fclose($handle);

                if ($chunk !== false && $chunk !== '') {
                    $this->output->write($chunk);
                }

                $position = $size;
            }

            usleep(400000);
        }
    }

    private function supportsPail(): bool
    {
        return extension_loaded('pcntl')
            && class_exists(\Laravel\Pail\PailServiceProvider::class);
    }
}
