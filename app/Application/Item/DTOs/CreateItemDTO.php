<?php

namespace App\Application\Item\DTOs;

class CreateItemDTO
{
    public function __construct(
        public string $name,
        public string $code,
        public string $type,
        public string $measureUnit,
        public float $minimumQuantity,
        public ?string $description,
        public ?float $unitPrice
    ) {}
}
