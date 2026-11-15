<?php

namespace App\Application\Item\UseCases;

use App\Application\Item\DTOs\CreateItemDTO;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;

class CreateItemUseCase
{
    public function __construct(
        private ItemRepositoryInterface $repository
    ) {}

    public function execute(CreateItemDTO $dto): Item
    {
        $code = new ItemCode($dto->code);

        if ($this->repository->findByCode($code->getValue())) {
            throw new \DomainException('An item with this code already exists.');
        }

        $item = Item::create(
            name: $dto->name,
            code: $code,
            type: new ItemType($dto->type),
            measureUnit: new MeasureUnit($dto->measureUnit),
            minimumQuantity: $dto->minimumQuantity,
            description: $dto->description,
            unitPrice: $dto->unitPrice
        );

        $this->repository->save($item);

        return $item;
    }
}
