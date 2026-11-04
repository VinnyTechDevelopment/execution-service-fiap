<?php

namespace App\Application\Execution\UseCases;

use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;

class QueryExecutionUseCase
{
    public function __construct(private ExecutionJobRepositoryInterface $jobs) {}

    /**
     * Fila de execução (ativos, mais antigos primeiro) ou filtrada por status.
     *
     * @return ExecutionJob[]
     */
    public function queue(?string $status = null): array
    {
        $statuses = $status !== null ? [$status] : ExecutionJob::ACTIVE_STATUSES;

        return $this->jobs->listByStatuses($statuses);
    }

    public function show(string $serviceOrderId): ExecutionJob
    {
        return $this->jobs->findByServiceOrderId($serviceOrderId)
            ?? throw new \DomainException('OS nao encontrada na fila de execucao.');
    }

    /**
     * Tempo médio (minutos) de execução por serviço, a partir dos jobs finalizados.
     *
     * @return array<int, array{service_id: string, name: string, executions: int, average_minutes: float}>
     */
    public function averageExecutionTime(): array
    {
        $totals = [];

        foreach ($this->jobs->listFinished() as $job) {
            foreach ($job->services as $service) {
                if ($service['started_at'] === null || $service['finished_at'] === null) {
                    continue;
                }

                $minutes = (strtotime($service['finished_at']) - strtotime($service['started_at'])) / 60;
                $id = $service['service_id'];
                $totals[$id] ??= ['service_id' => $id, 'name' => $service['name'], 'executions' => 0, 'sum' => 0.0];
                $totals[$id]['executions']++;
                $totals[$id]['sum'] += max(0, $minutes);
            }
        }

        return array_values(array_map(static fn (array $t) => [
            'service_id' => $t['service_id'],
            'name' => $t['name'],
            'executions' => $t['executions'],
            'average_minutes' => round($t['sum'] / $t['executions'], 2),
        ], $totals));
    }
}
