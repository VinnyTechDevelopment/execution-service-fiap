<?php

namespace App\Infrastructure\Messaging;

use App\Application\Messaging\Message;
use App\Infrastructure\Observability\Tracer;
use Illuminate\Support\Facades\Log;

/**
 * Laço de consumo de uma fila. Em caso de erro a mensagem NÃO é apagada:
 * volta a ficar visível após o visibility timeout e, depois de N tentativas,
 * a redrive policy do SQS a move para a DLQ.
 */
class MessageConsumer
{
    public function __construct(
        private QueueTransportInterface $transport,
        private MessageDispatcher $dispatcher,
        private Tracer $tracer,
    ) {}

    /**
     * Processa um lote. Retorna quantas mensagens foram processadas com sucesso.
     */
    public function consumeBatch(string $queue, int $maxMessages = 10, int $waitSeconds = 20): int
    {
        $processed = 0;

        foreach ($this->transport->receive($queue, $maxMessages, $waitSeconds) as $received) {
            try {
                $message = Message::fromJson($received->body);
            } catch (\InvalidArgumentException $e) {
                // Mensagem venenosa: nunca vai ser processável, não adianta reentregar.
                Log::error('messaging.invalid_message', ['queue' => $queue, 'error' => $e->getMessage()]);
                $this->transport->delete($queue, $received);

                continue;
            }

            $this->tracer->startBackgroundTransaction("Consume/{$message->type}");

            try {
                $this->dispatcher->dispatch($message);
                $this->transport->delete($queue, $received);
                $processed++;
            } catch (\Throwable $e) {
                $this->tracer->noticeError($e);
                Log::error('messaging.handler_failed', [
                    'queue' => $queue,
                    'type' => $message->type,
                    'message_id' => $message->id,
                    'saga_id' => $message->sagaId,
                    'receive_count' => $received->receiveCount,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                $this->tracer->endTransaction();
            }
        }

        return $processed;
    }
}
