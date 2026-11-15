<?php

namespace App\Domain\Item\ValueObjects;

use InvalidArgumentException;

class MovementType
{
    public const ENTRY = 'entry';

    public const WITHDRAWAL = 'withdrawal';

    private const VALID_TYPES = [self::ENTRY, self::WITHDRAWAL];

    private string $value;

    public function __construct(string $type)
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                'Invalid movement type. Accepted values: '.implode(', ', self::VALID_TYPES)
            );
        }

        $this->value = $type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isEntry(): bool
    {
        return $this->value === self::ENTRY;
    }

    public function isWithdrawal(): bool
    {
        return $this->value === self::WITHDRAWAL;
    }
}
