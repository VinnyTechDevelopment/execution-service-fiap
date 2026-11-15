<?php

namespace App\Infrastructure\Observability;

/**
 * Fachada fina sobre a API do agente PHP do New Relic.
 *
 * - Propaga o contexto de distributed tracing pelas mensagens SQS
 *   (insert/accept trace headers), para que uma OS apareça como um único
 *   trace atravessando os-service -> billing -> execution.
 * - Registra eventos customizados (ex.: "SagaStep") consultáveis via NRQL.
 *
 * Sem a extensão carregada (dev local, testes) todos os métodos são no-op.
 */
class Tracer
{
    public function __construct(private ?bool $enabled = null) {}

    public function enabled(): bool
    {
        return $this->enabled ??= extension_loaded('newrelic');
    }

    /**
     * Cabeçalhos W3C/New Relic do trace atual, para viajar junto da mensagem.
     */
    public function outgoingHeaders(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $headers = [];
        \newrelic_insert_distributed_trace_headers($headers);

        return $headers;
    }

    public function acceptHeaders(array $headers): void
    {
        if ($this->enabled() && $headers !== []) {
            \newrelic_accept_distributed_trace_headers($headers, 'Queue');
        }
    }

    /**
     * Abre uma transação "background" por mensagem consumida (o worker é um
     * processo de longa duração; sem isso tudo cairia numa transação só).
     */
    public function startBackgroundTransaction(string $name): void
    {
        if (! $this->enabled()) {
            return;
        }

        \newrelic_end_transaction();
        \newrelic_start_transaction((string) ini_get('newrelic.appname'));
        \newrelic_name_transaction($name);
        \newrelic_background_job(true);
    }

    public function endTransaction(): void
    {
        if ($this->enabled()) {
            \newrelic_end_transaction();
        }
    }

    public function addAttributes(array $attributes): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach ($attributes as $key => $value) {
            if (is_scalar($value)) {
                \newrelic_add_custom_parameter((string) $key, $value);
            }
        }
    }

    public function recordEvent(string $eventType, array $attributes): void
    {
        if ($this->enabled()) {
            \newrelic_record_custom_event($eventType, array_filter($attributes, 'is_scalar'));
        }
    }

    public function noticeError(\Throwable $e): void
    {
        if ($this->enabled()) {
            \newrelic_notice_error($e);
        }
    }
}
