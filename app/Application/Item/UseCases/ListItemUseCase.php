<?php

namespace App\Application\Item\UseCases;

use App\Application\Item\DTOs\ListItemDTO;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;

class ListItemUseCase
{
    public function __construct(
        private ItemRepositoryInterface $repository
    ) {}

    public function execute(ListItemDTO $dto): array
    {
        if ($dto->page !== null) {
            $perPage = ($dto->perPage > 0) ? $dto->perPage : 10;

            return $this->repository->paginate($dto->page, $perPage, $dto->type);
        }

        return $this->repository->findAll($dto->type);
    }
}
