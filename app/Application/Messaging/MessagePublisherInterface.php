<?php

namespace App\Application\Messaging;

interface MessagePublisherInterface
{
    /**
     * Publica a mensagem na fila lógica informada (ver MessageTypes::QUEUE_*).
     */
    public function publish(string $queue, Message $message): void;
}
