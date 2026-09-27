<?php

namespace App\Mail;

use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        $company = AppSetting::bill();

        return new Envelope(
            subject: ($company['name'] ?? config('app.name')).' — password reset code',
        );
    }

    public function content(): Content
    {
        $company = AppSetting::bill();
        $logoPath = $this->resolveLogoPath($company['logo'] ?? null);

        return new Content(
            view: 'mail.password-reset-code',
            with: [
                'code' => $this->code,
                'appName' => $company['name'] ?? config('app.name', 'ZUHA PERFUME'),
                'slogan' => $company['slogan'] ?? '',
                'phone' => $company['support_phone'] ?? ($company['phone'] ?? ''),
                'address' => $company['address'] ?? '',
                'manufacturer' => $company['manufacturer'] ?? 'Tezhoshan',
                'logoPath' => $logoPath,
                'expiresMinutes' => 10,
            ],
        );
    }

    protected function resolveLogoPath(?string $logo): string
    {
        if (is_string($logo) && str_starts_with($logo, '/storage/')) {
            $relative = ltrim(substr($logo, strlen('/storage/')), '/');
            $stored = storage_path('app/public/'.$relative);
            if (is_file($stored)) {
                return $stored;
            }
        }

        if (is_string($logo) && str_starts_with($logo, '/')) {
            $public = public_path(ltrim($logo, '/'));
            if (is_file($public) && filesize($public) < 400_000) {
                return $public;
            }
        }

        foreach ([
            public_path('images/apple-touch-icon.png'),
            public_path('images/favicon-48.png'),
            public_path('images/logo.png'),
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return public_path('images/favicon-48.png');
    }
}
