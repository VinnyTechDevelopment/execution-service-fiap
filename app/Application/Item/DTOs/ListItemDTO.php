<?php

namespace App\Application\Item\DTOs;

class ListItemDTO
{
    public function __construct(
        public ?int $page,
        public ?int $perPage,
        public ?string $type
    ) {}
}
