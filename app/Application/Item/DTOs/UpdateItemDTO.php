<?php

namespace App\Application\Item\DTOs;

class UpdateItemDTO
{
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $code,
        public ?string $type,
        public ?string $measureUnit,
        public ?float $minimumQuantity,
        public ?string $description,
        public ?float $unitPrice
    ) {}
}
