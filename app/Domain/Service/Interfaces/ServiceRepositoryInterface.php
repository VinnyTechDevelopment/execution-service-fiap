<?php

namespace App\Domain\Service\Interfaces;

use App\Domain\Service\Entities\Service;

interface ServiceRepositoryInterface
{
    public function save(Service $service): void;

    /**
     * @return Service[]
     */
    public function findAll(): array;

    public function findByName(string $name): ?Service;

    public function paginate(int $page, int $perPage): array;

    public function findById(string $id): ?Service;

    public function update(Service $service): void;

    public function delete(string $id): void;
}
