<?php

namespace App\Infrastructure\Messaging;

final class ReceivedMessage
{
    public function __construct(
        public readonly string $body,
        public readonly string $receiptHandle,
        public readonly int $receiveCount = 1,
    ) {}
}
