<?php

namespace Tests\Unit\Domain\Item\ValueObjects;

use App\Domain\Item\ValueObjects\MovementType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MovementTypeTest extends TestCase
{
    public function test_accepts_entry(): void
    {
        $type = new MovementType(MovementType::ENTRY);

        $this->assertSame('entry', $type->getValue());
        $this->assertTrue($type->isEntry());
        $this->assertFalse($type->isWithdrawal());
    }

    public function test_accepts_withdrawal(): void
    {
        $type = new MovementType(MovementType::WITHDRAWAL);

        $this->assertSame('withdrawal', $type->getValue());
        $this->assertTrue($type->isWithdrawal());
        $this->assertFalse($type->isEntry());
    }

    #[DataProvider('invalidTypes')]
    public function test_rejects_invalid_type(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid movement type.');

        new MovementType($value);
    }

    public static function invalidTypes(): array
    {
        return [
            'empty string' => [''],
            'uppercase' => ['ENTRY'],
            'unknown value' => ['transfer'],
            'numeric' => ['0'],
        ];
    }
}
