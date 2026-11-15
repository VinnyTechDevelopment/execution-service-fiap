<?php

namespace Tests\Support;

use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Exceptions\ConcurrentStockUpdateException;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

/**
 * Implementações em memória dos repositórios (mesma semântica dos de
 * DynamoDB, inclusive o controle otimista de estoque) para testes de
 * aplicação e de API.
 */
class InMemoryItemRepository implements ItemRepositoryInterface
{
    /** @var array<string, Item> */
    public array $items = [];

    public function save(Item $item): void
    {
        $this->items[$item->id] = clone $item;
    }

    public function findById(string $id): ?Item
    {
        return isset($this->items[$id]) ? clone $this->items[$id] : null;
    }

    public function findByCode(string $code): ?Item
    {
        foreach ($this->items as $item) {
            if ($item->code->getValue() === strtoupper($code)) {
                return clone $item;
            }
        }

        return null;
    }

    public function paginate(int $page, int $perPage, ?string $type): array
    {
        $all = $this->findAll($type);

        return ['data' => array_slice($all, ($page - 1) * $perPage, $perPage), 'total' => count($all), 'page' => $page, 'perPage' => $perPage];
    }

    public function findAll(?string $type): array
    {
        return array_values(array_map(static fn (Item $i) => [
            'id' => $i->id, 'name' => $i->name, 'code' => $i->code->getValue(), 'type' => $i->type->getValue(),
            'stock_quantity' => $i->stockQuantity, 'unit_price' => $i->unitPrice,
        ], array_filter($this->items, static fn (Item $i) => $type === null || $i->type->getValue() === $type)));
    }

    public function update(Item $item): void
    {
        $this->items[$item->id] = clone $item;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }
}

class InMemoryServiceRepository implements ServiceRepositoryInterface
{
    /** @var array<string, Service> */
    public array $services = [];

    public function save(Service $service): void
    {
        $this->services[$service->id] = clone $service;
    }

    public function findAll(): array
    {
        return array_values(array_map(static fn (Service $s) => ['id' => $s->id, 'name' => $s->name, 'price' => $s->price], $this->services));
    }

    public function findByName(string $name): ?Service
    {
        foreach ($this->services as $service) {
            if ($service->name === $name) {
                return clone $service;
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
        return isset($this->services[$id]) ? clone $this->services[$id] : null;
    }

    public function update(Service $service): void
    {
        $this->services[$service->id] = clone $service;
    }

    public function delete(string $id): void
    {
        unset($this->services[$id]);
    }
}

class InMemoryStockMovementRepository implements StockMovementRepositoryInterface
{
    /** @var StockMovement[] */
    public array $movements = [];

    public function save(StockMovement $movement): void
    {
        $this->movements[] = $movement;
    }

    public function findByItemId(string $itemId, int $page, int $perPage): array
    {
        $rows = array_values(array_filter($this->movements, static fn (StockMovement $m) => $m->itemId === $itemId));

        return ['data' => array_map(static fn (StockMovement $m) => ['id' => $m->id, 'type' => $m->movementType->getValue(), 'quantity' => $m->quantity], array_slice($rows, ($page - 1) * $perPage, $perPage)), 'total' => count($rows), 'page' => $page, 'perPage' => $perPage];
    }
}

class InMemoryExecutionJobRepository implements ExecutionJobRepositoryInterface
{
    /** @var array<string, ExecutionJob> */
    public array $jobs = [];

    /** Quantas vezes simular conflito de concorrência antes de aceitar. */
    public int $conflictsToSimulate = 0;

    public function __construct(
        private InMemoryItemRepository $items,
        private InMemoryStockMovementRepository $movements,
    ) {}

    public function findByServiceOrderId(string $serviceOrderId): ?ExecutionJob
    {
        return isset($this->jobs[$serviceOrderId]) ? clone $this->jobs[$serviceOrderId] : null;
    }

    public function listByStatuses(array $statuses): array
    {
        $jobs = array_values(array_filter($this->jobs, static fn (ExecutionJob $j) => in_array($j->status, $statuses, true)));
        usort($jobs, static fn (ExecutionJob $a, ExecutionJob $b) => strcmp($a->queuedAt, $b->queuedAt));

        return $jobs;
    }

    public function listFinished(): array
    {
        return $this->listByStatuses([ExecutionJob::STATUS_FINISHED]);
    }

    public function update(ExecutionJob $job): void
    {
        $this->jobs[$job->serviceOrderId] = clone $job;
    }

    public function saveWithStockMovements(ExecutionJob $job, array $movements, bool $isNew): void
    {
        if ($this->conflictsToSimulate > 0) {
            $this->conflictsToSimulate--;
            throw new ConcurrentStockUpdateException('conflito simulado');
        }

        if ($isNew && isset($this->jobs[$job->serviceOrderId])) {
            throw new ConcurrentStockUpdateException('job ja existe');
        }

        foreach ($movements as $movement) {
            $item = $this->items->items[$movement->itemId] ?? null;
            if (! $item || $item->stockQuantity !== $movement->previousQuantity) {
                throw new ConcurrentStockUpdateException('saldo mudou');
            }
        }

        foreach ($movements as $movement) {
            $this->items->items[$movement->itemId]->stockQuantity = $movement->currentQuantity;
            $this->movements->save($movement);
        }

        $this->jobs[$job->serviceOrderId] = clone $job;
    }
}
