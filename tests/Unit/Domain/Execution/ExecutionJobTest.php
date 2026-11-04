<?php

namespace Tests\Unit\Domain\Execution;

use App\Domain\Execution\Entities\ExecutionJob;
use PHPUnit\Framework\TestCase;

class ExecutionJobTest extends TestCase
{
    private function job(): ExecutionJob
    {
        return ExecutionJob::enqueue(
            'os-1',
            'saga-1',
            [['service_id' => 's1', 'name' => 'Troca de oleo', 'quantity' => 1]],
            [['item_id' => 'i1', 'quantity' => 2]],
            '2026-11-04T10:00:00+00:00'
        );
    }

    public function test_enqueue_starts_queued_with_normalized_lines(): void
    {
        $job = $this->job();

        $this->assertSame(ExecutionJob::STATUS_QUEUED, $job->status);
        $this->assertTrue($job->isActive());
        $this->assertNull($job->services[0]['started_at']);
        $this->assertSame(2.0, $job->reservations[0]['quantity']);
        $this->assertSame('os-1', $job->toArray()['service_order_id']);
    }

    public function test_enqueue_requires_services(): void
    {
        $this->expectException(\DomainException::class);
        ExecutionJob::enqueue('os', 'saga', [], [], 'now');
    }

    public function test_full_lifecycle(): void
    {
        $job = $this->job();
        $job->startDiagnosis('t1', 'verificar motor');
        $this->assertSame(ExecutionJob::STATUS_IN_DIAGNOSIS, $job->status);
        $this->assertSame('verificar motor', $job->notes);

        $job->startRepair('t2');
        $this->assertSame(ExecutionJob::STATUS_IN_REPAIR, $job->status);
        $this->assertSame('verificar motor', $job->notes);

        $job->finish('t3', 'ok');
        $this->assertSame(ExecutionJob::STATUS_FINISHED, $job->status);
        $this->assertSame('t3', $job->finishedAt);
        $this->assertSame('t2', $job->services[0]['started_at'], 'servico nao apontado usa o inicio do reparo');
        $this->assertSame('t3', $job->services[0]['finished_at']);
        $this->assertFalse($job->isActive());
    }

    public function test_cannot_skip_steps(): void
    {
        $job = $this->job();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("esperado 'in_diagnosis'");
        $job->startRepair('t');
    }

    public function test_cannot_finish_before_repair(): void
    {
        $this->expectException(\DomainException::class);
        $this->job()->finish('t');
    }

    public function test_cancel_returns_reservations_and_is_terminal(): void
    {
        $job = $this->job();
        $released = $job->cancel('t');

        $this->assertSame([['item_id' => 'i1', 'quantity' => 2.0]], $released);
        $this->assertSame(ExecutionJob::STATUS_CANCELLED, $job->status);

        $this->expectException(\DomainException::class);
        $job->cancel('t');
    }

    public function test_service_tracking(): void
    {
        $job = $this->job();
        $job->startDiagnosis('t1');
        $job->startRepair('t2');

        $job->startService('s1', 't3');
        $job->finishService('s1', 't4');
        $this->assertSame('t3', $job->services[0]['started_at']);
        $this->assertSame('t4', $job->services[0]['finished_at']);

        $job->finish('t5');
        $this->assertSame('t4', $job->services[0]['finished_at'], 'finish nao sobrescreve apontamento existente');
    }

    public function test_service_tracking_errors(): void
    {
        $job = $this->job();

        try {
            $job->startService('s1', 't');
            $this->fail('precisa estar em reparo');
        } catch (\DomainException) {
        }

        $job->startDiagnosis('t');
        $job->startRepair('t');

        foreach ([
            fn () => $job->startService('nao-existe', 't'),
            fn () => $job->finishService('s1', 't'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('deveria falhar');
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }

        $job->startService('s1', 't');
        try {
            $job->startService('s1', 't');
            $this->fail('ja iniciado');
        } catch (\DomainException) {
        }

        $job->finishService('s1', 't');
        $this->expectException(\DomainException::class);
        $job->finishService('s1', 't');
    }

    public function test_invalid_status_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ExecutionJob('os', 'saga', 'flying', [], [], 'now');
    }
}
