<?php

namespace App\Console\Commands;

use App\Infrastructure\Messaging\MessageConsumer;
use Illuminate\Console\Command;

class ConsumeMessagesCommand extends Command
{
    protected $signature = 'messaging:consume
        {--queue= : Fila lógica a consumir (padrão: messaging.inbound_queue)}
        {--once : Processa um único lote e sai}
        {--wait=20 : Long polling em segundos}
        {--max=10 : Mensagens por lote}';

    protected $description = 'Consome a fila de entrada deste microsserviço (SQS) e despacha para os handlers';

    private bool $shouldStop = false;

    public function handle(MessageConsumer $consumer): int
    {
        $queue = (string) ($this->option('queue') ?: config('messaging.inbound_queue'));

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
        }

        $this->info("Consumindo a fila '{$queue}'...");

        do {
            $count = $consumer->consumeBatch($queue, (int) $this->option('max'), (int) $this->option('wait'));

            if ($count > 0) {
                $this->line("{$count} mensagem(ns) processada(s).");
            }
        } while (! $this->option('once') && ! $this->shouldStop);

        return self::SUCCESS;
    }
}
