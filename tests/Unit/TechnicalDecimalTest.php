<?php

namespace Tests\Unit;

use App\Support\TechnicalDecimal;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TechnicalDecimalTest extends TestCase
{
    public static function comparisons(): array
    {
        return [
            ['-999999999999.999999', '999999999999.999999', -1],
            ['999999999999.999999', '999999999999.999998', 1],
            ['-0.000001', '-0.000002', 1],
            ['0.000001', '0', 1],
            ['-1', '-1', 0],
            ['0', '0', 0],
            ['2', '10', -1],
        ];
    }

    #[DataProvider('comparisons')]
    public function test_comparisons_preserve_sign_and_last_digit(string $left, string $right, int $expected): void
    {
        $this->assertSame($expected, TechnicalDecimal::compare($left, $right));
    }

    public function test_zero_and_trailing_zeros_are_normalized_without_floats(): void
    {
        $this->assertSame('0', TechnicalDecimal::normalize('-000.000000', 'min'));
        $this->assertSame('1', TechnicalDecimal::normalize('01.000000', 'min', 0));
        $this->assertSame('-1.25', TechnicalDecimal::normalize('-1.250000', 'min', 2));
        $this->assertNull(TechnicalDecimal::normalize(null, 'min'));
        $this->assertNull(TechnicalDecimal::normalize('', 'min'));
    }

    public function test_significant_precision_is_not_rounded_to_fit_the_unit(): void
    {
        $this->expectException(ValidationException::class);
        TechnicalDecimal::normalize('1.001000', 'min', 2);
    }
}
