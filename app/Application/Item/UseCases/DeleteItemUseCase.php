<?php

namespace App\Application\Item\UseCases;

use App\Domain\Item\Interfaces\ItemRepositoryInterface;

class DeleteItemUseCase
{
    public function __construct(
        private ItemRepositoryInterface $repository
    ) {}

    public function execute(string $id): void
    {
        $item = $this->repository->findById($id);

        if (! $item) {
            throw new \DomainException('Item not found.');
        }

        if ($item->stockQuantity > 0) {
            throw new \DomainException('Cannot delete an item with available stock.');
        }

        $this->repository->delete($id);
    }
}
