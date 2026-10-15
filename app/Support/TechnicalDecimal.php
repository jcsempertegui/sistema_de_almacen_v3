<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class TechnicalDecimal
{
    // DECIMAL(18,6): compare canonical strings, never floats or platform-sized integers.
    public static function normalize(mixed $value, string $field, int $precision = 6): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,6}))?$/D', (string) $value, $parts)) {
            throw ValidationException::withMessages([$field => 'Usa un decimal con punto, hasta 12 enteros y 6 decimales; sin separadores ni notación científica.']);
        }
        $integer = ltrim($parts[2], '0') ?: '0';
        $fraction = rtrim($parts[3] ?? '', '0');
        if (strlen($fraction) > $precision) {
            throw ValidationException::withMessages([$field => "La unidad admite hasta {$precision} decimales significativos."]);
        }
        $sign = $parts[1] && ($integer !== '0' || $fraction !== '') ? '-' : '';

        return $sign.$integer.($fraction !== '' ? '.'.$fraction : '');
    }

    public static function compare(string $left, string $right): int
    {
        $negativeLeft = str_starts_with($left, '-');
        $negativeRight = str_starts_with($right, '-');
        if ($negativeLeft !== $negativeRight) {
            return $negativeLeft ? -1 : 1;
        }
        $partsLeft = explode('.', ltrim($left, '-'));
        $partsRight = explode('.', ltrim($right, '-'));
        $a = str_pad($partsLeft[0], 12, '0', STR_PAD_LEFT).str_pad($partsLeft[1] ?? '', 6, '0');
        $b = str_pad($partsRight[0], 12, '0', STR_PAD_LEFT).str_pad($partsRight[1] ?? '', 6, '0');

        return (strcmp($a, $b) <=> 0) * ($negativeLeft ? -1 : 1);
    }
}
