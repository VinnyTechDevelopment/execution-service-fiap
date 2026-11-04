<?php

namespace App\Infrastructure\Messaging;

/**
 * Converte o nome lógico da fila ("billing-commands") no nome físico do
 * ambiente ("production-billing-commands"). Assim produção e homologação
 * compartilham a conta AWS sem misturar mensagens.
 */
final class QueueNameResolver
{
    public function __construct(private string $prefix) {}

    public function resolve(string $queue): string
    {
        return $this->prefix === '' ? $queue : "{$this->prefix}-{$queue}";
    }
}
