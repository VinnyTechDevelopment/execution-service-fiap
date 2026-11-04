<?php

namespace App\Domain\Item\Entities;

use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use Illuminate\Support\Str;

class Item
{
    public function __construct(
        public string $id,
        public string $name,
        public ItemCode $code,
        public ItemType $type,
        public MeasureUnit $measureUnit,
        public float $stockQuantity,
        public float $minimumQuantity,
        public ?string $description,
        public ?float $unitPrice
    ) {}

    public static function create(
        string $name,
        ItemCode $code,
        ItemType $type,
        MeasureUnit $measureUnit,
        float $minimumQuantity,
        ?string $description,
        ?float $unitPrice
    ): self {
        return new self(
            id: Str::uuid()->toString(),
            name: $name,
            code: $code,
            type: $type,
            measureUnit: $measureUnit,
            stockQuantity: 0.0,
            minimumQuantity: $minimumQuantity,
            description: $description,
            unitPrice: $unitPrice
        );
    }

    public function updateData(
        ?string $name,
        ?ItemCode $code,
        ?ItemType $type,
        ?MeasureUnit $measureUnit,
        ?float $minimumQuantity,
        ?string $description,
        ?float $unitPrice
    ): void {
        if ($name !== null) {
            $this->name = $name;
        }
        if ($code !== null) {
            $this->code = $code;
        }
        if ($type !== null) {
            $this->type = $type;
        }
        if ($measureUnit !== null) {
            $this->measureUnit = $measureUnit;
        }
        if ($minimumQuantity !== null) {
            $this->minimumQuantity = $minimumQuantity;
        }
        if ($description !== null) {
            $this->description = $description;
        }
        if ($unitPrice !== null) {
            $this->unitPrice = $unitPrice;
        }
    }

    public function addStock(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Stock entry quantity must be greater than zero.');
        }

        $this->stockQuantity += $quantity;
    }

    public function removeStock(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Stock withdrawal quantity must be greater than zero.');
        }

        if ($quantity > $this->stockQuantity) {
            throw new \DomainException(
                "Insufficient stock. Available: {$this->stockQuantity}, requested: {$quantity}."
            );
        }

        $this->stockQuantity -= $quantity;
    }

    public function isLowStock(): bool
    {
        return $this->stockQuantity <= $this->minimumQuantity;
    }
}
