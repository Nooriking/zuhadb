<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $company = config('app.name', 'ZUHA PERFUME');
        $holder = $user->email ?: $user->username;
        $otpauth = $this->google2fa->getQRCodeUrl($company, $holder, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($otpauth);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($code === '' || strlen($code) !== 6) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return Collection::times($count, fn () => Str::lower(Str::random(10).'-'.Str::random(10)))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $codes
     */
    public function recoveryCodeMatches(array $codes, string $provided): bool
    {
        $provided = Str::lower(trim($provided));

        foreach ($codes as $code) {
            if (hash_equals(Str::lower($code), $provided)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function consumeRecoveryCode(array $codes, string $provided): array
    {
        $provided = Str::lower(trim($provided));

        return array_values(array_filter(
            $codes,
            fn (string $code) => ! hash_equals(Str::lower($code), $provided)
        ));
    }
}
