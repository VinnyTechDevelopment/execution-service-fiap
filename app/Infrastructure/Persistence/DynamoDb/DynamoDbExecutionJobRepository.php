<?php

namespace App\Infrastructure\Persistence\DynamoDb;

use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Exceptions\ConcurrentStockUpdateException;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;
use App\Domain\Item\Entities\StockMovement;
use Aws\DynamoDb\Exception\DynamoDbException;

/**
 * Tabela "jobs": PK service_order_id. GSI "status-index" (PK status,
 * SK queued_at) para montar a fila de execução em ordem de chegada.
 */
class DynamoDbExecutionJobRepository implements ExecutionJobRepositoryInterface
{
    public function __construct(private DynamoDbTables $db) {}

    public function findByServiceOrderId(string $serviceOrderId): ?ExecutionJob
    {
        $row = $this->db->get('jobs', ['service_order_id' => $serviceOrderId]);

        return $row ? $this->toEntity($row) : null;
    }

    public function listByStatuses(array $statuses): array
    {
        $jobs = [];

        foreach ($statuses as $status) {
            $rows = $this->db->queryAll('jobs', [
                'IndexName' => 'status-index',
                'KeyConditionExpression' => '#status = :status',
                'ExpressionAttributeNames' => ['#status' => 'status'],
                'ExpressionAttributeValues' => [':status' => $this->db->marshalValue($status)],
            ]);

            foreach ($rows as $row) {
                $jobs[] = $this->toEntity($row);
            }
        }

        usort($jobs, static fn (ExecutionJob $a, ExecutionJob $b) => strcmp($a->queuedAt, $b->queuedAt));

        return $jobs;
    }

    public function listFinished(): array
    {
        return $this->listByStatuses([ExecutionJob::STATUS_FINISHED]);
    }

    public function update(ExecutionJob $job): void
    {
        $this->db->put('jobs', $this->toItem($job), 'attribute_exists(service_order_id)');
    }

    public function saveWithStockMovements(ExecutionJob $job, array $movements, bool $isNew): void
    {
        $now = now()->toIso8601String();

        $actions = [[
            'Put' => array_filter([
                'TableName' => $this->db->name('jobs'),
                'Item' => $this->db->marshal($this->toItem($job)),
                // Garante que a mesma OS não entra duas vezes na fila.
                'ConditionExpression' => $isNew ? 'attribute_not_exists(service_order_id)' : 'attribute_exists(service_order_id)',
            ]),
        ]];

        /** @var StockMovement $movement */
        foreach ($movements as $movement) {
            $actions[] = [
                'Update' => [
                    'TableName' => $this->db->name('items'),
                    'Key' => $this->db->marshal(['id' => $movement->itemId]),
                    'UpdateExpression' => 'SET stock_quantity = :current, updated_at = :now',
                    // Controle otimista: só aplica se ninguém mexeu no saldo desde a leitura.
                    'ConditionExpression' => 'attribute_exists(id) AND stock_quantity = :previous',
                    'ExpressionAttributeValues' => $this->db->marshal([
                        ':current' => $movement->currentQuantity,
                        ':previous' => $movement->previousQuantity,
                        ':now' => $now,
                    ]),
                ],
            ];

            $actions[] = [
                'Put' => [
                    'TableName' => $this->db->name('stock_movements'),
                    'Item' => $this->db->marshal(DynamoDbStockMovementRepository::toItem($movement, $now)),
                ],
            ];
        }

        try {
            $this->db->client->transactWriteItems(['TransactItems' => $actions]);
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() === 'TransactionCanceledException') {
                throw new ConcurrentStockUpdateException(
                    "Transacao de estoque cancelada para a OS {$job->serviceOrderId}: {$e->getAwsErrorMessage()}",
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    private function toItem(ExecutionJob $job): array
    {
        return $job->toArray() + ['updated_at' => now()->toIso8601String()];
    }

    private function toEntity(array $row): ExecutionJob
    {
        return new ExecutionJob(
            serviceOrderId: (string) $row['service_order_id'],
            sagaId: (string) $row['saga_id'],
            status: (string) $row['status'],
            services: array_map(static fn (array $s) => [
                'service_id' => (string) $s['service_id'],
                'name' => (string) ($s['name'] ?? ''),
                'quantity' => (float) ($s['quantity'] ?? 1),
                'started_at' => $s['started_at'] ?? null,
                'finished_at' => $s['finished_at'] ?? null,
            ], $row['services'] ?? []),
            reservations: array_map(static fn (array $r) => [
                'item_id' => (string) $r['item_id'],
                'quantity' => (float) $r['quantity'],
            ], $row['reservations'] ?? []),
            queuedAt: (string) $row['queued_at'],
            diagnosisStartedAt: $row['diagnosis_started_at'] ?? null,
            repairStartedAt: $row['repair_started_at'] ?? null,
            finishedAt: $row['finished_at'] ?? null,
            cancelledAt: $row['cancelled_at'] ?? null,
            notes: $row['notes'] ?? null,
        );
    }
}
