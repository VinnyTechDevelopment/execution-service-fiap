<?php

namespace App\Application\Item\DTOs;

class StockWithdrawalDTO
{
    public function __construct(
        public string $itemId,
        public float $quantity,
        public string $reason,
        public ?string $notes
    ) {}
}
