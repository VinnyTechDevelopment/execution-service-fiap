<?php

namespace App\Infrastructure\Messaging;

use App\Application\Messaging\Message;
use App\Application\Messaging\MessageHandlerInterface;
use App\Application\Messaging\ProcessedMessageStoreInterface;
use App\Infrastructure\Observability\Tracer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Roteia cada mensagem recebida para o handler registrado em
 * config('messaging.handlers'), de forma idempotente.
 */
class MessageDispatcher
{
    /**
     * @param  array<string, class-string<MessageHandlerInterface>>  $handlers
     */
    public function __construct(
        private Container $container,
        private ProcessedMessageStoreInterface $processed,
        private Tracer $tracer,
        private array $handlers,
    ) {}

    public function dispatch(Message $message): void
    {
        $this->tracer->acceptHeaders($message->traceHeaders);
        $this->tracer->addAttributes([
            'messageType' => $message->type,
            'messageId' => $message->id,
            'sagaId' => $message->sagaId,
            'correlationId' => $message->correlationId,
        ]);

        $handlerClass = $this->handlers[$message->type] ?? null;

        if ($handlerClass === null) {
            Log::warning('messaging.unknown_type', ['type' => $message->type, 'message_id' => $message->id]);

            return;
        }

        $executed = $this->processed->runOnce($message, function () use ($handlerClass, $message): void {
            /** @var MessageHandlerInterface $handler */
            $handler = $this->container->make($handlerClass);
            $handler->handle($message);
        });

        Log::info($executed ? 'messaging.handled' : 'messaging.duplicate_ignored', [
            'type' => $message->type,
            'message_id' => $message->id,
            'saga_id' => $message->sagaId,
        ]);
    }
}
