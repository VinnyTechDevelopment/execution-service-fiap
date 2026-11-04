<?php

namespace App\Application\Item\UseCases;

use App\Application\Item\DTOs\UpdateItemDTO;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;

class UpdateItemUseCase
{
    public function __construct(
        private ItemRepositoryInterface $repository
    ) {}

    public function execute(UpdateItemDTO $dto): Item
    {
        $item = $this->repository->findById($dto->id);

        if (! $item) {
            throw new \DomainException('Item not found.');
        }

        $newCode = null;
        if ($dto->code !== null) {
            $newCode = new ItemCode($dto->code);
            $existing = $this->repository->findByCode($newCode->getValue());

            if ($existing && $existing->id !== $item->id) {
                throw new \DomainException('Another item already uses this code.');
            }
        }

        $item->updateData(
            name: $dto->name,
            code: $newCode,
            type: $dto->type !== null ? new ItemType($dto->type) : null,
            measureUnit: $dto->measureUnit !== null ? new MeasureUnit($dto->measureUnit) : null,
            minimumQuantity: $dto->minimumQuantity,
            description: $dto->description,
            unitPrice: $dto->unitPrice
        );

        $this->repository->update($item);

        return $item;
    }
}
