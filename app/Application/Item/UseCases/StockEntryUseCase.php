<?php

namespace App\Application\Item\UseCases;

use App\Application\Item\DTOs\StockEntryDTO;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\ValueObjects\MovementType;

class StockEntryUseCase
{
    public function __construct(
        private ItemRepositoryInterface $itemRepository,
        private StockMovementRepositoryInterface $movementRepository
    ) {}

    public function execute(StockEntryDTO $dto): StockMovement
    {
        $item = $this->itemRepository->findById($dto->itemId);

        if (! $item) {
            throw new \DomainException('Item not found.');
        }

        $previousQuantity = $item->stockQuantity;

        $item->addStock($dto->quantity);

        $this->itemRepository->update($item);

        $movement = StockMovement::record(
            itemId: $item->id,
            movementType: new MovementType(MovementType::ENTRY),
            quantity: $dto->quantity,
            previousQuantity: $previousQuantity,
            currentQuantity: $item->stockQuantity,
            reason: $dto->reason,
            notes: $dto->notes
        );

        $this->movementRepository->save($movement);

        return $movement;
    }
}
