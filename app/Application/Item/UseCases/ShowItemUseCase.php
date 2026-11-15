<?php

namespace App\Application\Item\UseCases;

use App\Application\Item\DTOs\ShowItemDTO;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;

class ShowItemUseCase
{
    public function __construct(
        private ItemRepositoryInterface $repository
    ) {}

    public function execute(ShowItemDTO $dto): Item
    {
        $item = $this->repository->findById($dto->id);

        if (! $item) {
            throw new \DomainException('Item not found.');
        }

        return $item;
    }
}
