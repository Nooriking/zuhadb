<?php

namespace App\Support;

class Money
{
    public static function mul(string $left, string $right, int $scale = 8): string
    {
        if (function_exists('bcmul')) {
            return bcmul($left, $right, $scale);
        }

        return self::formatFloat((float) $left * (float) $right, $scale);
    }

    public static function div(string $left, string $right, int $scale = 8): string
    {
        if ((float) $right == 0.0) {
            throw new \InvalidArgumentException('Cannot divide by zero.');
        }

        if (function_exists('bcdiv')) {
            return bcdiv($left, $right, $scale);
        }

        return self::formatFloat((float) $left / (float) $right, $scale);
    }

    public static function round(string $amount, int $decimals): string
    {
        $decimals = max(0, $decimals);

        if (function_exists('bcadd')) {
            $half = $decimals === 0
                ? '0.5'
                : '0.'.str_repeat('0', $decimals - 1).'5';

            $signedHalf = str_starts_with(ltrim($amount), '-')
                ? '-'.$half
                : $half;

            return bcadd($amount, $signedHalf, $decimals);
        }

        return self::formatFloat((float) $amount, $decimals);
    }

    public static function format(string|int|float $amount, int $decimals, ?string $symbol = null, ?string $code = null): string
    {
        $formatted = number_format((float) $amount, max(0, $decimals), '.', ',');
        $suffix = $symbol ?: $code;

        return $suffix ? $formatted.' '.$suffix : $formatted;
    }

    private static function formatFloat(float $value, int $scale): string
    {
        return number_format($value, $scale, '.', '');
    }
}
