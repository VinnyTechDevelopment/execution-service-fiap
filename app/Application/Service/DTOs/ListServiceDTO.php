<?php

namespace App\Application\Service\DTOs;

class ListServiceDTO
{
    public function __construct(
        public ?int $page,
        public ?int $perPage
    ) {}
}
