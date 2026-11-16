<?php

namespace App\Support;

/**
 * Immutable three-currency rate board for a single transaction.
 *
 * Base rates (authoritative):
 * - usd_to_afn: AFN per 1 USD
 * - aed_to_afn: AFN per 1 AED
 *
 * Derived (never independently authoritative):
 * - usd_to_aed = usd_to_afn / aed_to_afn
 */
final class FxBoard
{
    public function __construct(
        public readonly string $usdToAfn,
        public readonly string $aedToAfn,
        public readonly string $usdToAed,
        public readonly ?string $effectiveAt,
        public readonly string $source,
        public readonly string $kind = 'operational',
    ) {}

    /**
     * @param  array{
     *     usd_to_afn: string,
     *     aed_to_afn: string,
     *     usd_to_aed?: string|null,
     *     effective_at?: string|null,
     *     source?: string|null,
     *     kind?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $usdToAfn = Money::round((string) $data['usd_to_afn'], 8);
        $aedToAfn = Money::round((string) $data['aed_to_afn'], 8);

        if ((float) $usdToAfn <= 0 || (float) $aedToAfn <= 0) {
            throw new \InvalidArgumentException('FX board rates must be greater than zero.');
        }

        $derived = Money::round(Money::div($usdToAfn, $aedToAfn, 12), 8);
        $provided = isset($data['usd_to_aed']) && (float) $data['usd_to_aed'] > 0
            ? Money::round((string) $data['usd_to_aed'], 8)
            : $derived;

        return new self(
            usdToAfn: $usdToAfn,
            aedToAfn: $aedToAfn,
            usdToAed: $provided,
            effectiveAt: $data['effective_at'] ?? null,
            source: (string) ($data['source'] ?? 'operational'),
            kind: (string) ($data['kind'] ?? 'operational'),
        );
    }

    /**
     * Convert an original amount in AFN/USD/AED into all three equivalents.
     *
     * @return array{afn: string, usd: string, aed: string}
     */
    public function equivalents(string $amount, string $currencyCode, int $decimals = 2): array
    {
        $code = strtoupper(trim($currencyCode));
        $amount = Money::round($amount === '' ? '0' : $amount, 8);

        $afn = match ($code) {
            'AFN' => $amount,
            'USD' => Money::mul($amount, $this->usdToAfn, 12),
            'AED' => Money::mul($amount, $this->aedToAfn, 12),
            default => throw new \InvalidArgumentException("Unsupported currency code: {$code}"),
        };

        $afn = Money::round($afn, $decimals);
        $usd = Money::round(Money::div($afn, $this->usdToAfn, 12), $decimals);
        $aed = Money::round(Money::div($afn, $this->aedToAfn, 12), $decimals);

        // Preserve the exact original when converting from that currency.
        if ($code === 'USD') {
            $usd = Money::round($amount, $decimals);
        } elseif ($code === 'AED') {
            $aed = Money::round($amount, $decimals);
        } elseif ($code === 'AFN') {
            $afn = Money::round($amount, $decimals);
        }

        return [
            'afn' => $afn,
            'usd' => $usd,
            'aed' => $aed,
        ];
    }

    /**
     * Rate: 1 unit of $code = X AFN.
     */
    public function rateToAfn(string $currencyCode): string
    {
        return match (strtoupper(trim($currencyCode))) {
            'AFN' => '1.00000000',
            'USD' => $this->usdToAfn,
            'AED' => $this->aedToAfn,
            default => throw new \InvalidArgumentException('Unsupported currency code.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'usd_to_afn_rate' => $this->usdToAfn,
            'aed_to_afn_rate' => $this->aedToAfn,
            'usd_to_aed_rate' => $this->usdToAed,
            'rate_effective_at' => $this->effectiveAt,
            'rate_source' => $this->source,
        ];
    }
}
