<?php

namespace App\Infrastructure\Persistence\DynamoDb;

use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;

/**
 * Tabela "stock_movements": PK item_id, SK created_at_id (ordenação
 * cronológica dentro da partição de cada peça).
 */
class DynamoDbStockMovementRepository implements StockMovementRepositoryInterface
{
    public function __construct(private DynamoDbTables $db) {}

    public function save(StockMovement $movement): void
    {
        $this->db->put('stock_movements', self::toItem($movement, now()->toIso8601String()));
    }

    public function findByItemId(string $itemId, int $page, int $perPage): array
    {
        $rows = $this->db->queryAll('stock_movements', [
            'KeyConditionExpression' => 'item_id = :item',
            'ExpressionAttributeValues' => [':item' => $this->db->marshalValue($itemId)],
            'ScanIndexForward' => false,
        ]);

        $data = array_map(static fn (array $m) => [
            'id' => $m['id'],
            'type' => $m['movement_type'],
            'quantity' => (float) $m['quantity'],
            'previous_quantity' => (float) $m['previous_quantity'],
            'current_quantity' => (float) $m['current_quantity'],
            'reason' => $m['reason'],
            'notes' => $m['notes'] ?? null,
            'service_order_id' => $m['service_order_id'] ?? null,
            'created_at' => $m['created_at'],
        ], array_slice($rows, ($page - 1) * $perPage, $perPage));

        return [
            'data' => $data,
            'total' => count($rows),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public static function toItem(StockMovement $movement, string $createdAt): array
    {
        return [
            'item_id' => $movement->itemId,
            'created_at_id' => "{$createdAt}#{$movement->id}",
            'id' => $movement->id,
            'service_order_id' => $movement->serviceOrderId,
            'movement_type' => $movement->movementType->getValue(),
            'quantity' => $movement->quantity,
            'previous_quantity' => $movement->previousQuantity,
            'current_quantity' => $movement->currentQuantity,
            'reason' => $movement->reason,
            'notes' => $movement->notes,
            'created_at' => $createdAt,
        ];
    }
}
