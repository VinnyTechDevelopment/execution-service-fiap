<?php

namespace App\Application\Service\DTOs;

class CreateServiceDTO
{
    public function __construct(
        public string $name,
        public string $price
    ) {}
}
