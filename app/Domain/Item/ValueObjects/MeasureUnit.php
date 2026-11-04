<?php

namespace App\Domain\Item\ValueObjects;

use InvalidArgumentException;

class MeasureUnit
{
    public const UNIT = 'un';

    public const KILOGRAM = 'kg';

    public const GRAM = 'g';

    public const LITER = 'l';

    public const MILLILITER = 'ml';

    public const METER = 'm';

    public const CENTIMETER = 'cm';

    public const BOX = 'cx';

    public const PAIR = 'pair';

    public const PIECE = 'pc';

    private const VALID_UNITS = [
        self::UNIT, self::KILOGRAM, self::GRAM, self::LITER, self::MILLILITER,
        self::METER, self::CENTIMETER, self::BOX, self::PAIR, self::PIECE,
    ];

    private string $value;

    public function __construct(string $unit)
    {
        $unit = strtolower(trim($unit));

        if (! in_array($unit, self::VALID_UNITS, true)) {
            throw new InvalidArgumentException(
                'Invalid measure unit. Accepted values: '.implode(', ', self::VALID_UNITS)
            );
        }

        $this->value = $unit;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
