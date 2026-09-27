<?php

namespace App\Support\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Shared backend data-format + safety rules.
 * Keep money/qty/text bounds and anti-HTML constraints in one place.
 */
final class SafeData
{
    public const MONEY_MAX = 999_999_999.99;

    public const QTY_MAX = 1_000_000;

    public const NOTES_MAX = 2000;

    public const TITLE_MAX = 255;

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function money(bool $required = true, float $min = 0): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'numeric',
            'min:'.$min,
            'max:'.self::MONEY_MAX,
        ]));
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function quantity(bool $required = true, int $min = 1): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'integer',
            'min:'.$min,
            'max:'.self::QTY_MAX,
        ]));
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function plainText(int $max = self::NOTES_MAX, bool $required = false): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'max:'.$max,
            'regex:/^[^<>\x00-\x08\x0B\x0C\x0E-\x1F]*$/u',
        ]));
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function title(bool $required = true, int $max = self::TITLE_MAX): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'max:'.$max,
            'regex:/^[^<>\x00-\x08\x0B\x0C\x0E-\x1F]*$/u',
        ]));
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function username(bool $required = true): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'min:3',
            'max:100',
            'regex:/^[A-Za-z0-9._-]+$/',
        ]));
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function phone(bool $required = false): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'min:7',
            'max:50',
            'regex:/^[0-9+\-\s()]+$/',
        ]));
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function email(bool $required = false): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'email:filter',
            'max:255',
        ]));
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function paymentMethod(): array
    {
        return ['required', Rule::in(['cash', 'card', 'bank', 'mobile', 'credit', 'other'])];
    }

    public static function strongPassword(): Password
    {
        return Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    /**
     * Trim strings and strip dangerous control / HTML angle brackets from scalar input.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input, array $preserveKeys = ['password', 'password_confirmation', 'current_password']): array
    {
        foreach ($input as $key => $value) {
            if (in_array($key, $preserveKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $input[$key] = self::sanitize($value, $preserveKeys);
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $value = str_replace("\0", '', $value);
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;
            // Neutralize HTML/script injection vectors in stored text.
            $value = str_replace(['<', '>'], '', $value);

            $input[$key] = $value === '' ? null : $value;
        }

        return $input;
    }
}
