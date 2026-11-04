<?php

namespace App\Application\Service\UseCases;

use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

class DeleteServiceUseCase
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(string $id): void
    {
        $service = $this->repository->findById($id);

        if (! $service) {
            throw new \DomainException('Service not found.');
        }

        $this->repository->delete($id);
    }
}
