<?php

namespace App\Infrastructure\Messaging;

use App\Application\Messaging\Message;
use App\Application\Messaging\MessagePublisherInterface;
use App\Infrastructure\Observability\Tracer;
use Illuminate\Support\Facades\Log;

/**
 * Publica direto no transporte (sem outbox). Também é usado pelo relay do
 * outbox para efetivamente enviar as mensagens gravadas.
 */
class DirectMessagePublisher implements MessagePublisherInterface
{
    public function __construct(
        private QueueTransportInterface $transport,
        private Tracer $tracer,
        private string $source,
    ) {}

    public function publish(string $queue, Message $message): void
    {
        $message = $this->prepare($message);

        $this->transport->send($queue, $message->toJson(), [
            'type' => $message->type,
            'saga_id' => $message->sagaId,
        ]);

        Log::info('messaging.published', [
            'queue' => $queue,
            'type' => $message->type,
            'message_id' => $message->id,
            'saga_id' => $message->sagaId,
        ]);
    }

    public function prepare(Message $message): Message
    {
        if ($message->source === '') {
            $message = $message->withSource($this->source);
        }

        if ($message->traceHeaders === []) {
            $message = $message->withTraceHeaders($this->tracer->outgoingHeaders());
        }

        return $message;
    }
}
