<?php

namespace App\Application\Messaging;

use Illuminate\Support\Str;

/**
 * Envelope padrão das mensagens trocadas entre os microsserviços (comandos e
 * eventos da Saga). O mesmo formato é usado nos três repositórios — ver
 * docs/arquitetura/contratos-mensagens.md.
 */
final class Message
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $sagaId,
        public readonly string $correlationId,
        public readonly array $payload,
        public readonly string $occurredAt,
        public readonly string $source = '',
        public readonly array $traceHeaders = [],
    ) {
        if ($type === '') {
            throw new \InvalidArgumentException('Tipo da mensagem e obrigatorio.');
        }

        if ($sagaId === '') {
            throw new \InvalidArgumentException('saga_id da mensagem e obrigatorio.');
        }
    }

    public static function create(string $type, string $sagaId, array $payload, ?string $correlationId = null): self
    {
        return new self(
            id: Str::uuid()->toString(),
            type: $type,
            sagaId: $sagaId,
            correlationId: $correlationId ?? $sagaId,
            payload: $payload,
            occurredAt: now()->toIso8601String(),
        );
    }

    /**
     * Resposta a uma mensagem recebida: mantém saga_id e correlation_id.
     */
    public function reply(string $type, array $payload): self
    {
        return self::create($type, $this->sagaId, $payload, $this->correlationId);
    }

    public function withSource(string $source): self
    {
        return new self($this->id, $this->type, $this->sagaId, $this->correlationId, $this->payload, $this->occurredAt, $source, $this->traceHeaders);
    }

    public function withTraceHeaders(array $traceHeaders): self
    {
        return new self($this->id, $this->type, $this->sagaId, $this->correlationId, $this->payload, $this->occurredAt, $this->source, $traceHeaders);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    public function toArray(): array
    {
        return [
            'message_id' => $this->id,
            'type' => $this->type,
            'saga_id' => $this->sagaId,
            'correlation_id' => $this->correlationId,
            'source' => $this->source,
            'occurred_at' => $this->occurredAt,
            'trace_headers' => $this->traceHeaders,
            'payload' => $this->payload,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function fromArray(array $data): self
    {
        foreach (['message_id', 'type', 'saga_id', 'payload'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("Mensagem invalida: campo '{$field}' ausente.");
            }
        }

        if (! is_array($data['payload'])) {
            throw new \InvalidArgumentException("Mensagem invalida: 'payload' deve ser um objeto.");
        }

        return new self(
            id: (string) $data['message_id'],
            type: (string) $data['type'],
            sagaId: (string) $data['saga_id'],
            correlationId: (string) ($data['correlation_id'] ?? $data['saga_id']),
            payload: $data['payload'],
            occurredAt: (string) ($data['occurred_at'] ?? now()->toIso8601String()),
            source: (string) ($data['source'] ?? ''),
            traceHeaders: is_array($data['trace_headers'] ?? null) ? $data['trace_headers'] : [],
        );
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Mensagem invalida: JSON malformado.', 0, $e);
        }

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Mensagem invalida: esperado um objeto JSON.');
        }

        return self::fromArray($data);
    }
}
