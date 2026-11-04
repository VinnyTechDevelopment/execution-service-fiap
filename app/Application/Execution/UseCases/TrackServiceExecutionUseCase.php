<?php

namespace App\Application\Execution\UseCases;

use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;

/**
 * Apontamento de início/fim de cada serviço da OS durante o reparo — base da
 * métrica de tempo médio de execução por serviço.
 */
class TrackServiceExecutionUseCase
{
    public function __construct(private ExecutionJobRepositoryInterface $jobs) {}

    public function start(string $serviceOrderId, string $serviceId): ExecutionJob
    {
        $job = $this->find($serviceOrderId);
        $job->startService($serviceId, now()->toIso8601String());
        $this->jobs->update($job);

        return $job;
    }

    public function finish(string $serviceOrderId, string $serviceId): ExecutionJob
    {
        $job = $this->find($serviceOrderId);
        $job->finishService($serviceId, now()->toIso8601String());
        $this->jobs->update($job);

        return $job;
    }

    private function find(string $serviceOrderId): ExecutionJob
    {
        return $this->jobs->findByServiceOrderId($serviceOrderId)
            ?? throw new \DomainException('OS nao encontrada na fila de execucao.');
    }
}
