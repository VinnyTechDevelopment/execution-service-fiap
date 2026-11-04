<?php

namespace Tests\Unit\Domain\Item\ValueObjects;

use App\Domain\Item\ValueObjects\ItemType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ItemTypeTest extends TestCase
{
    public function test_accepts_part(): void
    {
        $type = new ItemType(ItemType::PART);

        $this->assertSame('part', $type->getValue());
        $this->assertTrue($type->isPart());
        $this->assertFalse($type->isSupply());
    }

    public function test_accepts_supply(): void
    {
        $type = new ItemType(ItemType::SUPPLY);

        $this->assertSame('supply', $type->getValue());
        $this->assertTrue($type->isSupply());
        $this->assertFalse($type->isPart());
    }

    #[DataProvider('invalidTypes')]
    public function test_rejects_invalid_type(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid item type.');

        new ItemType($value);
    }

    public static function invalidTypes(): array
    {
        return [
            'empty string' => [''],
            'uppercase' => ['PART'],
            'unknown value' => ['tool'],
            'numeric' => ['1'],
        ];
    }
}
