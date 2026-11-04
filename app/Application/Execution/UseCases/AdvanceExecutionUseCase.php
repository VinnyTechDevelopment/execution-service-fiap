<?php

namespace App\Application\Execution\UseCases;

use App\Application\Messaging\Message;
use App\Application\Messaging\MessagePublisherInterface;
use App\Application\Messaging\MessageTypes;
use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;

/**
 * Atualiza o status durante diagnóstico e reparo e comunica o os-service
 * (ExecutionStatusChanged / ExecutionFinished).
 */
class AdvanceExecutionUseCase
{
    public const ACTION_DIAGNOSIS = 'diagnosis';

    public const ACTION_REPAIR = 'repair';

    public const ACTION_FINISH = 'finish';

    public function __construct(
        private ExecutionJobRepositoryInterface $jobs,
        private MessagePublisherInterface $publisher,
    ) {}

    public function execute(string $serviceOrderId, string $action, ?string $notes = null): ExecutionJob
    {
        $job = $this->jobs->findByServiceOrderId($serviceOrderId);

        if (! $job) {
            throw new \DomainException('OS nao encontrada na fila de execucao.');
        }

        $now = now()->toIso8601String();

        match ($action) {
            self::ACTION_DIAGNOSIS => $job->startDiagnosis($now, $notes),
            self::ACTION_REPAIR => $job->startRepair($now, $notes),
            self::ACTION_FINISH => $job->finish($now, $notes),
            default => throw new \InvalidArgumentException("Acao de execucao invalida: {$action}"),
        };

        $this->jobs->update($job);

        $type = $action === self::ACTION_FINISH
            ? MessageTypes::EXECUTION_FINISHED
            : MessageTypes::EXECUTION_STATUS_CHANGED;

        $this->publisher->publish(MessageTypes::QUEUE_OS_SAGA_REPLIES, Message::create($type, $job->sagaId, [
            'service_order_id' => $job->serviceOrderId,
            'status' => $job->status,
            'notes' => $job->notes,
            'occurred_at' => $now,
        ]));

        return $job;
    }
}
