<?php

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\CreateServiceDTO;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

class CreateServiceUseCase
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(CreateServiceDTO $dto): Service
    {
        $service = Service::create(
            $dto->name,
            $dto->price
        );

        $existing = $this->repository->findByName($dto->name);

        if ($existing) {
            throw new \Exception('Serviço já cadastrado');
        }

        $this->repository->save($service);

        return $service;
    }
}
