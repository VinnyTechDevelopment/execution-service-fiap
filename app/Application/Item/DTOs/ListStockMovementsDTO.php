<?php

namespace App\Application\Item\DTOs;

class ListStockMovementsDTO
{
    public function __construct(
        public string $itemId,
        public int $page,
        public int $perPage
    ) {}
}
