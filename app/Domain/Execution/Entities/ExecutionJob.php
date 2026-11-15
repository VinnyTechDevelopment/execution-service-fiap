<?php

namespace App\Domain\Execution\Entities;

/**
 * Item da fila de execução da oficina: uma OS cujo pagamento foi confirmado
 * e cujas peças já foram reservadas no estoque.
 *
 * Ciclo de vida: queued -> in_diagnosis -> in_repair -> finished
 *                (queued|in_diagnosis|in_repair) -> cancelled (compensação)
 */
class ExecutionJob
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_IN_DIAGNOSIS = 'in_diagnosis';

    public const STATUS_IN_REPAIR = 'in_repair';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_IN_DIAGNOSIS,
        self::STATUS_IN_REPAIR,
    ];

    /**
     * @param  array<int, array{service_id: string, name: string, quantity: float, started_at: ?string, finished_at: ?string}>  $services
     * @param  array<int, array{item_id: string, quantity: float}>  $reservations
     */
    public function __construct(
        public readonly string $serviceOrderId,
        public readonly string $sagaId,
        public string $status,
        public array $services,
        public array $reservations,
        public readonly string $queuedAt,
        public ?string $diagnosisStartedAt = null,
        public ?string $repairStartedAt = null,
        public ?string $finishedAt = null,
        public ?string $cancelledAt = null,
        public ?string $notes = null,
    ) {
        if (! in_array($status, [...self::ACTIVE_STATUSES, self::STATUS_FINISHED, self::STATUS_CANCELLED], true)) {
            throw new \InvalidArgumentException("Status de execucao invalido: {$status}");
        }
    }

    public static function enqueue(
        string $serviceOrderId,
        string $sagaId,
        array $services,
        array $reservations,
        string $now,
    ): self {
        if ($services === []) {
            throw new \DomainException('A OS precisa de ao menos um servico para entrar na fila de execucao.');
        }

        $normalizedServices = array_map(static fn (array $s) => [
            'service_id' => (string) $s['service_id'],
            'name' => (string) ($s['name'] ?? ''),
            'quantity' => (float) ($s['quantity'] ?? 1),
            'started_at' => null,
            'finished_at' => null,
        ], $services);

        $normalizedReservations = array_map(static fn (array $r) => [
            'item_id' => (string) $r['item_id'],
            'quantity' => (float) $r['quantity'],
        ], $reservations);

        return new self(
            serviceOrderId: $serviceOrderId,
            sagaId: $sagaId,
            status: self::STATUS_QUEUED,
            services: array_values($normalizedServices),
            reservations: array_values($normalizedReservations),
            queuedAt: $now,
        );
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function startDiagnosis(string $now, ?string $notes = null): void
    {
        $this->assertStatus(self::STATUS_QUEUED, 'iniciar o diagnostico');
        $this->status = self::STATUS_IN_DIAGNOSIS;
        $this->diagnosisStartedAt = $now;
        $this->notes = $notes ?? $this->notes;
    }

    public function startRepair(string $now, ?string $notes = null): void
    {
        $this->assertStatus(self::STATUS_IN_DIAGNOSIS, 'iniciar o reparo');
        $this->status = self::STATUS_IN_REPAIR;
        $this->repairStartedAt = $now;
        $this->notes = $notes ?? $this->notes;
    }

    public function finish(string $now, ?string $notes = null): void
    {
        $this->assertStatus(self::STATUS_IN_REPAIR, 'finalizar a execucao');

        foreach ($this->services as &$service) {
            $service['started_at'] ??= $this->repairStartedAt ?? $now;
            $service['finished_at'] ??= $now;
        }
        unset($service);

        $this->status = self::STATUS_FINISHED;
        $this->finishedAt = $now;
        $this->notes = $notes ?? $this->notes;
    }

    /**
     * Compensação da saga: devolve as reservas que precisam voltar ao estoque.
     *
     * @return array<int, array{item_id: string, quantity: float}>
     */
    public function cancel(string $now): array
    {
        if (! $this->isActive()) {
            throw new \DomainException("Execucao em status '{$this->status}' nao pode ser cancelada.");
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = $now;

        return $this->reservations;
    }

    public function startService(string $serviceId, string $now): void
    {
        $this->assertStatus(self::STATUS_IN_REPAIR, 'iniciar um servico');
        $index = $this->serviceIndex($serviceId);

        if ($this->services[$index]['started_at'] !== null) {
            throw new \DomainException('Servico ja foi iniciado.');
        }

        $this->services[$index]['started_at'] = $now;
    }

    public function finishService(string $serviceId, string $now): void
    {
        $this->assertStatus(self::STATUS_IN_REPAIR, 'finalizar um servico');
        $index = $this->serviceIndex($serviceId);

        if ($this->services[$index]['started_at'] === null) {
            throw new \DomainException('Servico ainda nao foi iniciado.');
        }

        if ($this->services[$index]['finished_at'] !== null) {
            throw new \DomainException('Servico ja foi finalizado.');
        }

        $this->services[$index]['finished_at'] = $now;
    }

    public function toArray(): array
    {
        return [
            'service_order_id' => $this->serviceOrderId,
            'saga_id' => $this->sagaId,
            'status' => $this->status,
            'services' => $this->services,
            'reservations' => $this->reservations,
            'queued_at' => $this->queuedAt,
            'diagnosis_started_at' => $this->diagnosisStartedAt,
            'repair_started_at' => $this->repairStartedAt,
            'finished_at' => $this->finishedAt,
            'cancelled_at' => $this->cancelledAt,
            'notes' => $this->notes,
        ];
    }

    private function serviceIndex(string $serviceId): int
    {
        foreach ($this->services as $index => $service) {
            if ($service['service_id'] === $serviceId) {
                return $index;
            }
        }

        throw new \DomainException("Servico '{$serviceId}' nao pertence a esta execucao.");
    }

    private function assertStatus(string $expected, string $action): void
    {
        if ($this->status !== $expected) {
            throw new \DomainException("Nao e possivel {$action}: execucao esta em '{$this->status}', esperado '{$expected}'.");
        }
    }
}
