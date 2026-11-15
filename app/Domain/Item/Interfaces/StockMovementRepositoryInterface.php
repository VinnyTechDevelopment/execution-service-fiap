<?php

namespace App\Domain\Item\Interfaces;

use App\Domain\Item\Entities\StockMovement;

interface StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void;

    public function findByItemId(string $itemId, int $page, int $perPage): array;
}
