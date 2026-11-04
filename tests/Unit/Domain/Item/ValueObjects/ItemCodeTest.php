<?php

namespace Tests\Unit\Domain\Item\ValueObjects;

use App\Domain\Item\ValueObjects\ItemCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ItemCodeTest extends TestCase
{
    #[DataProvider('validCodes')]
    public function test_accepts_valid_codes(string $input, string $expected): void
    {
        $code = new ItemCode($input);

        $this->assertSame($expected, $code->getValue());
    }

    public static function validCodes(): array
    {
        return [
            'uppercase letters' => ['AB', 'AB'],
            'letters and numbers' => ['ITEM001', 'ITEM001'],
            'with hyphen' => ['BP-001', 'BP-001'],
            'auto-uppercases lowercase' => ['item-001', 'ITEM-001'],
            'trims whitespace' => ['  BP-001  ', 'BP-001'],
            'max length 30 chars' => [str_repeat('A', 30), str_repeat('A', 30)],
            'numbers only' => ['123', '123'],
        ];
    }

    #[DataProvider('invalidCodes')]
    public function test_rejects_invalid_codes(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid item code.');

        new ItemCode($input);
    }

    public static function invalidCodes(): array
    {
        return [
            'empty string' => [''],
            'single character' => ['A'],
            'exceeds 30 chars' => [str_repeat('A', 31)],
            'contains space' => ['ITEM 001'],
            'contains special chars' => ['ITEM@001'],
            'contains dot' => ['ITEM.001'],
        ];
    }
}
