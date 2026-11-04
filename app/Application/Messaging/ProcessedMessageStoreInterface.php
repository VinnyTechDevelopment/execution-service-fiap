<?php

namespace App\Application\Messaging;

/**
 * Garante processamento idempotente: SQS entrega "at-least-once", então a
 * mesma mensagem pode chegar mais de uma vez.
 */
interface ProcessedMessageStoreInterface
{
    /**
     * Executa $handler uma única vez por message_id. Retorna false se a
     * mensagem já tinha sido processada (o handler não é executado).
     */
    public function runOnce(Message $message, callable $handler): bool;
}
