<?php

namespace App\Application\Execution\Handlers;

use App\Application\Execution\Services\StockReservationService;
use App\Application\Messaging\Message;
use App\Application\Messaging\MessageHandlerInterface;
use App\Application\Messaging\MessagePublisherInterface;
use App\Application\Messaging\MessageTypes;
use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;
use App\Infrastructure\Observability\Tracer;

/**
 * Passo da saga "enviar para execução": reserva as peças e coloca a OS na
 * fila. Responde ExecutionQueued ou PartsReservationFailed (que dispara a
 * compensação no orquestrador: estorno do pagamento + cancelamento).
 *
 * Idempotente: se o job já existe (reentrega após falha ao publicar a
 * resposta), apenas republica a resposta.
 */
class ReservePartsAndEnqueueHandler implements MessageHandlerInterface
{
    public function __construct(
        private ExecutionJobRepositoryInterface $jobs,
        private StockReservationService $reservations,
        private MessagePublisherInterface $publisher,
        private Tracer $tracer,
    ) {}

    public function handle(Message $message): void
    {
        $serviceOrderId = (string) $message->get('service_order_id');
        $existing = $this->jobs->findByServiceOrderId($serviceOrderId);

        if ($existing !== null) {
            $this->replyQueued($message, $existing);

            return;
        }

        try {
            $job = ExecutionJob::enqueue(
                serviceOrderId: $serviceOrderId,
                sagaId: $message->sagaId,
                services: (array) $message->get('services', []),
                reservations: (array) $message->get('items', []),
                now: now()->toIso8601String(),
            );

            $this->reservations->reserveAndEnqueue($job);
        } catch (\DomainException $e) {
            $this->tracer->recordEvent('ExecutionReservationFailed', [
                'serviceOrderId' => $serviceOrderId,
                'sagaId' => $message->sagaId,
                'reason' => $e->getMessage(),
            ]);

            $this->publisher->publish(MessageTypes::QUEUE_OS_SAGA_REPLIES, $message->reply(
                MessageTypes::EXECUTION_RESERVATION_FAILED,
                ['service_order_id' => $serviceOrderId, 'reason' => $e->getMessage()]
            ));

            return;
        }

        $this->replyQueued($message, $job);
    }

    private function replyQueued(Message $message, ExecutionJob $job): void
    {
        $this->publisher->publish(MessageTypes::QUEUE_OS_SAGA_REPLIES, $message->reply(
            MessageTypes::EXECUTION_QUEUED,
            [
                'service_order_id' => $job->serviceOrderId,
                'status' => $job->status,
                'queued_at' => $job->queuedAt,
            ]
        ));
    }
}
