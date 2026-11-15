<?php

namespace App\Application\Service\DTOs;

class UpdateServiceDTO
{
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $price
    ) {}
}
