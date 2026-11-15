<?php

namespace App\Infrastructure\Messaging;

interface QueueTransportInterface
{
    public function send(string $queue, string $body, array $attributes = []): void;

    /**
     * @return ReceivedMessage[]
     */
    public function receive(string $queue, int $maxMessages = 10, int $waitSeconds = 20): array;

    public function delete(string $queue, ReceivedMessage $message): void;
}
