<?php

namespace App\Application\Item\UseCases;

use App\Application\Item\DTOs\ListStockMovementsDTO;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;

class ListStockMovementsUseCase
{
    public function __construct(
        private ItemRepositoryInterface $itemRepository,
        private StockMovementRepositoryInterface $movementRepository
    ) {}

    public function execute(ListStockMovementsDTO $dto): array
    {
        $item = $this->itemRepository->findById($dto->itemId);

        if (! $item) {
            throw new \DomainException('Item not found.');
        }

        return $this->movementRepository->findByItemId($dto->itemId, $dto->page, $dto->perPage);
    }
}
