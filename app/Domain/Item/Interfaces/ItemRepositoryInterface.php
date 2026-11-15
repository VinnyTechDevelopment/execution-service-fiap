<?php

namespace App\Domain\Item\Interfaces;

use App\Domain\Item\Entities\Item;

interface ItemRepositoryInterface
{
    public function save(Item $item): void;

    public function findById(string $id): ?Item;

    public function findByCode(string $code): ?Item;

    public function paginate(int $page, int $perPage, ?string $type): array;

    public function findAll(?string $type): array;

    public function update(Item $item): void;

    public function delete(string $id): void;
}
