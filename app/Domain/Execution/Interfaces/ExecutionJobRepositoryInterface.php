<?php

namespace App\Domain\Execution\Interfaces;

use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Exceptions\ConcurrentStockUpdateException;
use App\Domain\Item\Entities\StockMovement;

interface ExecutionJobRepositoryInterface
{
    public function findByServiceOrderId(string $serviceOrderId): ?ExecutionJob;

    /**
     * Fila de execução: jobs nos status informados, mais antigos primeiro.
     *
     * @param  string[]  $statuses
     * @return ExecutionJob[]
     */
    public function listByStatuses(array $statuses): array;

    /**
     * @return ExecutionJob[] jobs finalizados (base da métrica de tempo médio)
     */
    public function listFinished(): array;

    public function update(ExecutionJob $job): void;

    /**
     * Grava o job e aplica as movimentações de estoque numa única transação
     * atômica. Cada movimentação só é aplicada se o saldo atual do item ainda
     * for igual ao previousQuantity dela (controle otimista de concorrência).
     *
     * @param  StockMovement[]  $movements
     *
     * @throws ConcurrentStockUpdateException quando outro processo alterou o estoque no meio
     */
    public function saveWithStockMovements(ExecutionJob $job, array $movements, bool $isNew): void;
}
