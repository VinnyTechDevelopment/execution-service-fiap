<?php

namespace App\Domain\Item\ValueObjects;

use InvalidArgumentException;

class ItemCode
{
    private string $value;

    public function __construct(string $code)
    {
        $code = strtoupper(trim($code));

        if (! preg_match('/^[A-Z0-9\-]{2,30}$/', $code)) {
            throw new InvalidArgumentException(
                'Invalid item code. Use only letters, numbers and hyphens (2-30 characters).'
            );
        }

        $this->value = $code;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
