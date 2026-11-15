<?php

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ShowServiceDTO;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

class ShowServiceUseCase
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(ShowServiceDTO $dto): Service
    {
        $service = $this->repository->findById($dto->id);

        if (! $service) {
            throw new \DomainException('Serviço não encontrado');
        }

        return $service;
    }
}
