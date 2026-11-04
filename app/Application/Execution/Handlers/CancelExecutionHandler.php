<?php

namespace App\Application\Execution\Handlers;

use App\Application\Execution\Services\StockReservationService;
use App\Application\Messaging\Message;
use App\Application\Messaging\MessageHandlerInterface;
use App\Application\Messaging\MessagePublisherInterface;
use App\Application\Messaging\MessageTypes;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;

/**
 * Compensação: tira a OS da fila e devolve as peças reservadas ao estoque.
 * Se não há job (a reserva nem chegou a acontecer) ou ele já foi cancelado,
 * apenas confirma — compensações precisam ser idempotentes.
 */
class CancelExecutionHandler implements MessageHandlerInterface
{
    public function __construct(
        private ExecutionJobRepositoryInterface $jobs,
        private StockReservationService $reservations,
        private MessagePublisherInterface $publisher,
    ) {}

    public function handle(Message $message): void
    {
        $serviceOrderId = (string) $message->get('service_order_id');
        $job = $this->jobs->findByServiceOrderId($serviceOrderId);

        if ($job !== null && $job->isActive()) {
            $this->reservations->releaseAndCancel($job, now()->toIso8601String());
        }

        $this->publisher->publish(MessageTypes::QUEUE_OS_SAGA_REPLIES, $message->reply(
            MessageTypes::EXECUTION_CANCELLED,
            [
                'service_order_id' => $serviceOrderId,
                'released' => $job?->reservations ?? [],
                'status' => $job?->status,
            ]
        ));
    }
}
