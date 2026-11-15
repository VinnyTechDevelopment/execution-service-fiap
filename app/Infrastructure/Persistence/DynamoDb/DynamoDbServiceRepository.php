<?php

namespace App\Infrastructure\Persistence\DynamoDb;

use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

/**
 * Tabela "services": PK id (catálogo de serviços/mão de obra).
 */
class DynamoDbServiceRepository implements ServiceRepositoryInterface
{
    public function __construct(private DynamoDbTables $db) {}

    public function save(Service $service): void
    {
        $this->db->put('services', [
            'id' => $service->id,
            'name' => $service->name,
            'price' => $service->price,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function findAll(): array
    {
        $rows = $this->db->scanAll('services');
        usort($rows, static fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        return array_map(static fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => (float) $row['price'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ], $rows);
    }

    public function findByName(string $name): ?Service
    {
        foreach ($this->findAll() as $row) {
            if ($row['name'] === $name) {
                return new Service($row['id'], $row['name'], $row['price']);
            }
        }

        return null;
    }

    public function paginate(int $page, int $perPage): array
    {
        return array_slice($this->findAll(), ($page - 1) * $perPage, $perPage);
    }

    public function findById(string $id): ?Service
    {
        $row = $this->db->get('services', ['id' => $id]);

        return $row ? new Service((string) $row['id'], (string) $row['name'], (float) $row['price']) : null;
    }

    public function update(Service $service): void
    {
        $existing = $this->db->get('services', ['id' => $service->id]);

        $this->db->put('services', [
            'id' => $service->id,
            'name' => $service->name,
            'price' => $service->price,
            'created_at' => $existing['created_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function delete(string $id): void
    {
        $this->db->delete('services', ['id' => $id]);
    }
}
