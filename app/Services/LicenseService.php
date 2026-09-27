<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class LicenseService
{
    public function isEnabled(): bool
    {
        return (bool) config('license.enabled');
    }

    public function isValid(): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        try {
            $payload = $this->readLicense();
        } catch (\Throwable) {
            return false;
        }

        return $this->verifyPayload($payload);
    }

    public function status(): array
    {
        $machineId = $this->machineId();
        $payload = null;
        $valid = false;
        $error = null;

        try {
            if (is_file((string) config('license.file'))) {
                $payload = $this->readLicense();
                $valid = $this->verifyPayload($payload);
            } else {
                $error = 'No license file installed.';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'enabled' => $this->isEnabled(),
            'valid' => $valid,
            'machine_id' => $machineId,
            'customer' => $payload['customer'] ?? null,
            'issued_at' => $payload['issued_at'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
            'error' => $valid ? null : $error,
            'support_phone' => config('license.support_phone'),
            'support_email' => config('license.support_email'),
        ];
    }

    /**
     * Stable fingerprint for this computer (not reversible to hardware details).
     */
    public function machineId(): string
    {
        $parts = [
            PHP_OS_FAMILY,
            php_uname('n'),
            php_uname('m'),
            $this->windowsVolumeSerial(),
            $this->primaryMacAddress(),
            $this->cpuId(),
        ];

        $raw = strtoupper(implode('|', array_filter($parts)));

        return strtoupper(substr(hash_hmac('sha256', $raw, (string) config('license.product')), 0, 32));
    }

    /**
     * Vendor-only: create a signed license key for a machine id.
     *
     * @return array{key: string, payload: array<string, mixed>}
     */
    public function issue(string $machineId, string $customer = 'Licensed shop', ?int $days = null): array
    {
        $machineId = strtoupper(preg_replace('/[^A-F0-9]/i', '', $machineId) ?? '');
        if (strlen($machineId) < 16) {
            throw new RuntimeException('Invalid machine id.');
        }

        $payload = [
            'product' => (string) config('license.product'),
            'machine_id' => $machineId,
            'customer' => trim($customer) !== '' ? trim($customer) : 'Licensed shop',
            'issued_at' => now()->toIso8601String(),
            'expires_at' => $days && $days > 0 ? now()->addDays($days)->toIso8601String() : null,
        ];
        $payload['signature'] = $this->sign($payload);

        return [
            'key' => $this->encodeKey($payload),
            'payload' => $payload,
        ];
    }

    /**
     * Install a license key on this machine (must match fingerprint).
     *
     * @return array<string, mixed>
     */
    public function activate(string $key): array
    {
        $payload = $this->decodeKey($key);

        if (! $this->verifyPayload($payload, requireCurrentMachine: true)) {
            throw new RuntimeException('License is invalid for this computer (or expired).');
        }

        $path = (string) config('license.file');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    public function clear(): void
    {
        $path = (string) config('license.file');
        if (is_file($path)) {
            File::delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function verifyPayload(array $payload, bool $requireCurrentMachine = true): bool
    {
        if (($payload['product'] ?? null) !== config('license.product')) {
            return false;
        }

        $signature = (string) ($payload['signature'] ?? '');
        $check = $payload;
        unset($check['signature']);

        if ($signature === '' || ! hash_equals($this->sign($check), $signature)) {
            return false;
        }

        if ($requireCurrentMachine && strtoupper((string) ($payload['machine_id'] ?? '')) !== $this->machineId()) {
            return false;
        }

        $expires = $payload['expires_at'] ?? null;
        if (is_string($expires) && $expires !== '' && now()->greaterThan($expires)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readLicense(): array
    {
        $path = (string) config('license.file');
        if (! is_file($path)) {
            throw new RuntimeException('License file missing.');
        }

        $json = File::get($path);
        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            throw new RuntimeException('License file is corrupt.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sign(array $payload): string
    {
        ksort($payload);
        $secret = (string) config('license.secret');
        if ($secret === '') {
            throw new RuntimeException('LICENSE_SECRET is not configured.');
        }

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), $secret);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function encodeKey(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $b64 = rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');

        // Single-line key (easy to copy/paste).
        return 'ZUHA-'.$b64;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeKey(string $key): array
    {
        $key = trim($key);
        $key = preg_replace('/^ZUHA-/i', '', $key) ?? $key;
        $key = preg_replace('/\s+/', '', $key) ?? $key;
        $b64 = strtr($key, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode($b64, true);
        if ($json === false) {
            throw new RuntimeException('License key format is invalid.');
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            throw new RuntimeException('License key payload is invalid.');
        }

        return $payload;
    }

    protected function windowsVolumeSerial(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $this->unixMachineId();
        }

        try {
            $out = [];
            @exec('cmd /c vol C: 2>NUL', $out);
            $text = implode(' ', $out);
            if (preg_match('/([A-F0-9]{4}-[A-F0-9]{4})/i', $text, $m)) {
                return strtoupper($m[1]);
            }
        } catch (\Throwable) {
            //
        }

        return null;
    }

    protected function unixMachineId(): ?string
    {
        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $path) {
            if (is_readable($path)) {
                $id = trim((string) @file_get_contents($path));
                if ($id !== '') {
                    return $id;
                }
            }
        }

        return null;
    }

    protected function primaryMacAddress(): ?string
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $out = [];
                @exec('getmac /fo csv /nh 2>NUL', $out);
                foreach ($out as $line) {
                    if (preg_match('/([0-9A-F]{2}(?:-[0-9A-F]{2}){5})/i', $line, $m)) {
                        return strtoupper($m[1]);
                    }
                }
            } else {
                $out = [];
                @exec('cat /sys/class/net/*/address 2>/dev/null', $out);
                foreach ($out as $line) {
                    $line = trim($line);
                    if ($line !== '' && $line !== '00:00:00:00:00:00') {
                        return strtoupper($line);
                    }
                }
            }
        } catch (\Throwable) {
            //
        }

        return null;
    }

    protected function cpuId(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        try {
            $out = [];
            @exec('wmic cpu get ProcessorId /value 2>NUL', $out);
            foreach ($out as $line) {
                if (stripos($line, 'ProcessorId=') === 0) {
                    $id = trim(substr($line, strlen('ProcessorId=')));
                    if ($id !== '') {
                        return strtoupper($id);
                    }
                }
            }
        } catch (\Throwable) {
            //
        }

        return null;
    }
}
