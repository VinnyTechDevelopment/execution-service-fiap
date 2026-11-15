<?php

namespace App\Application\Execution\Services;

use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Exceptions\ConcurrentStockUpdateException;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\MovementType;

/**
 * Reserva (baixa) e estorno de peças, sempre gravados junto com o job numa
 * transação atômica do DynamoDB. Em caso de corrida com outra atualização de
 * estoque, relê os saldos e tenta de novo.
 */
class StockReservationService
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private ItemRepositoryInterface $items,
        private ExecutionJobRepositoryInterface $jobs,
    ) {}

    /**
     * @throws \DomainException peça inexistente ou estoque insuficiente
     */
    public function reserveAndEnqueue(ExecutionJob $job): void
    {
        $this->withRetry(function () use ($job): void {
            $movements = $this->buildMovements(
                $job,
                MovementType::WITHDRAWAL,
                "Reserva para OS #{$job->serviceOrderId}"
            );

            $this->jobs->saveWithStockMovements($job, $movements, isNew: true);
        });
    }

    /**
     * Compensação: devolve ao estoque tudo que foi reservado e cancela o job.
     */
    public function releaseAndCancel(ExecutionJob $job, string $now): void
    {
        $job->cancel($now);

        $this->withRetry(function () use ($job): void {
            $movements = $this->buildMovements(
                $job,
                MovementType::ENTRY,
                "Estorno da reserva da OS #{$job->serviceOrderId} (saga compensada)"
            );

            $this->jobs->saveWithStockMovements($job, $movements, isNew: false);
        });
    }

    /**
     * @return StockMovement[]
     */
    private function buildMovements(ExecutionJob $job, string $type, string $reason): array
    {
        $quantities = [];
        foreach ($job->reservations as $reservation) {
            $quantities[$reservation['item_id']] = ($quantities[$reservation['item_id']] ?? 0) + $reservation['quantity'];
        }

        $movements = [];
        foreach ($quantities as $itemId => $quantity) {
            $item = $this->items->findById($itemId);

            if (! $item) {
                throw new \DomainException("Peca '{$itemId}' nao encontrada no estoque.");
            }

            $previous = $item->stockQuantity;
            $type === MovementType::WITHDRAWAL ? $item->removeStock($quantity) : $item->addStock($quantity);

            $movements[] = StockMovement::record(
                itemId: $item->id,
                movementType: new MovementType($type),
                quantity: $quantity,
                previousQuantity: $previous,
                currentQuantity: $item->stockQuantity,
                reason: $reason,
                notes: null,
                serviceOrderId: $job->serviceOrderId,
            );
        }

        return $movements;
    }

    private function withRetry(callable $operation): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $operation();

                return;
            } catch (ConcurrentStockUpdateException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }
}
