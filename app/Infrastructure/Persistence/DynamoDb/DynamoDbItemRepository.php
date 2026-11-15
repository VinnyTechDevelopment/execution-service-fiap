<?php

namespace App\Infrastructure\Persistence\DynamoDb;

use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;

/**
 * Tabela "items": PK id. GSI "code-index" (PK code) para busca por código.
 */
class DynamoDbItemRepository implements ItemRepositoryInterface
{
    public function __construct(private DynamoDbTables $db) {}

    public function save(Item $item): void
    {
        $this->db->put('items', $this->toItem($item) + ['created_at' => now()->toIso8601String()]);
    }

    public function findById(string $id): ?Item
    {
        $data = $this->db->get('items', ['id' => $id]);

        return $data ? $this->toEntity($data) : null;
    }

    public function findByCode(string $code): ?Item
    {
        $rows = $this->db->queryAll('items', [
            'IndexName' => 'code-index',
            'KeyConditionExpression' => '#code = :code',
            'ExpressionAttributeNames' => ['#code' => 'code'],
            'ExpressionAttributeValues' => [':code' => $this->db->marshalValue(strtoupper($code))],
        ]);

        return $rows ? $this->toEntity($rows[0]) : null;
    }

    public function paginate(int $page, int $perPage, ?string $type): array
    {
        $all = $this->findAll($type);

        return [
            'data' => array_slice($all, ($page - 1) * $perPage, $perPage),
            'total' => count($all),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function findAll(?string $type): array
    {
        $params = [];

        if ($type !== null) {
            $params = [
                'FilterExpression' => '#type = :type',
                'ExpressionAttributeNames' => ['#type' => 'type'],
                'ExpressionAttributeValues' => [':type' => $this->db->marshalValue($type)],
            ];
        }

        $rows = $this->db->scanAll('items', $params);
        usort($rows, static fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        return array_map(fn (array $row) => $this->present($row), $rows);
    }

    public function update(Item $item): void
    {
        $existing = $this->db->get('items', ['id' => $item->id]);

        $this->db->put('items', $this->toItem($item) + [
            'created_at' => $existing['created_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function delete(string $id): void
    {
        $this->db->delete('items', ['id' => $id]);
    }

    private function toItem(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code->getValue(),
            'type' => $item->type->getValue(),
            'description' => $item->description,
            'measure_unit' => $item->measureUnit->getValue(),
            'stock_quantity' => $item->stockQuantity,
            'minimum_quantity' => $item->minimumQuantity,
            'unit_price' => $item->unitPrice,
        ];
    }

    private function toEntity(array $row): Item
    {
        return new Item(
            id: (string) $row['id'],
            name: (string) $row['name'],
            code: new ItemCode((string) $row['code']),
            type: new ItemType((string) $row['type']),
            measureUnit: new MeasureUnit((string) $row['measure_unit']),
            stockQuantity: (float) $row['stock_quantity'],
            minimumQuantity: (float) $row['minimum_quantity'],
            description: $row['description'] ?? null,
            unitPrice: isset($row['unit_price']) ? (float) $row['unit_price'] : null,
        );
    }

    private function present(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'type' => $row['type'],
            'description' => $row['description'] ?? null,
            'measure_unit' => $row['measure_unit'],
            'stock_quantity' => (float) $row['stock_quantity'],
            'minimum_quantity' => (float) $row['minimum_quantity'],
            'unit_price' => isset($row['unit_price']) ? (float) $row['unit_price'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
