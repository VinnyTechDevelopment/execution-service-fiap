<?php

namespace App\Domain\Item\ValueObjects;

use InvalidArgumentException;

class ItemType
{
    public const PART = 'part';

    public const SUPPLY = 'supply';

    private const VALID_TYPES = [self::PART, self::SUPPLY];

    private string $value;

    public function __construct(string $type)
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                'Invalid item type. Accepted values: '.implode(', ', self::VALID_TYPES)
            );
        }

        $this->value = $type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isPart(): bool
    {
        return $this->value === self::PART;
    }

    public function isSupply(): bool
    {
        return $this->value === self::SUPPLY;
    }
}
