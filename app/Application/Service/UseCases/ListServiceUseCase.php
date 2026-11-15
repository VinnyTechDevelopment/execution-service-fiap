<?php

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ListServiceDTO;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

class ListServiceUseCase
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(ListServiceDTO $dto): array
    {
        if ($dto->page !== null) {
            $perPage = $dto->perPage ?? 10;

            if ($perPage <= 0) {
                $perPage = 10;
            }

            return $this->repository->paginate($dto->page, $perPage);
        }

        return $this->repository->findAll();
    }
}
