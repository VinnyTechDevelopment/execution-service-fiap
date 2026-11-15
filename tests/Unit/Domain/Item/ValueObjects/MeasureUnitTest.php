<?php

namespace Tests\Unit\Domain\Item\ValueObjects;

use App\Domain\Item\ValueObjects\MeasureUnit;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MeasureUnitTest extends TestCase
{
    #[DataProvider('validUnits')]
    public function test_accepts_valid_unit(string $input, string $expected): void
    {
        $unit = new MeasureUnit($input);

        $this->assertSame($expected, $unit->getValue());
    }

    public static function validUnits(): array
    {
        return [
            'unit' => ['un', 'un'],
            'kilogram' => ['kg', 'kg'],
            'gram' => ['g', 'g'],
            'liter' => ['l', 'l'],
            'milliliter' => ['ml', 'ml'],
            'meter' => ['m', 'm'],
            'centimeter' => ['cm', 'cm'],
            'box' => ['cx', 'cx'],
            'pair' => ['pair', 'pair'],
            'piece' => ['pc', 'pc'],
            'auto-lowercases' => ['KG', 'kg'],
            'trims whitespace' => ['  un  ', 'un'],
        ];
    }

    #[DataProvider('invalidUnits')]
    public function test_rejects_invalid_unit(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid measure unit.');

        new MeasureUnit($value);
    }

    public static function invalidUnits(): array
    {
        return [
            'empty string' => [''],
            'unknown unit' => ['oz'],
            'full word' => ['kilogram'],
            'numeric' => ['1'],
        ];
    }
}
