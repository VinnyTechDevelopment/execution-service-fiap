<?php

namespace App\Application\Messaging;

interface MessageHandlerInterface
{
    public function handle(Message $message): void;
}
