<?php

namespace Tests\Feature\Execution;

use App\Application\Messaging\MessageTypes;
use App\Domain\Execution\Entities\ExecutionJob;
use Firebase\JWT\JWT;
use Tests\Support\UsesInMemoryRepositories;
use Tests\TestCase;

class ExecutionApiTest extends TestCase
{
    use UsesInMemoryRepositories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindInMemoryRepositories();
    }

    private function givenQueuedJob(string $osId = 'os-1', string $queuedAt = '2026-11-04T10:00:00+00:00', string $status = ExecutionJob::STATUS_QUEUED): ExecutionJob
    {
        $job = ExecutionJob::enqueue($osId, "saga-{$osId}", [['service_id' => 's1', 'name' => 'Revisao', 'quantity' => 1]], [], $queuedAt);
        $job->status = $status;
        $this->jobRepo->update($job);

        return $job;
    }

    public function test_requires_staff_token(): void
    {
        $this->getJson('/api/execution/queue')->assertUnauthorized();
        $this->getJson('/api/execution/queue', ['Authorization' => 'Bearer invalido'])->assertUnauthorized();
    }

    public function test_rejects_token_without_subject_or_customer_token(): void
    {
        $secret = (string) config('services.staff_jwt.secret');

        $noSub = JWT::encode(['exp' => time() + 60], $secret, 'HS256');
        $this->getJson('/api/execution/queue', ['Authorization' => "Bearer {$noSub}"])->assertUnauthorized();

        $customer = JWT::encode(['sub' => 'c1', 'typ' => 'customer', 'exp' => time() + 60], $secret, 'HS256');
        $this->getJson('/api/execution/queue', ['Authorization' => "Bearer {$customer}"])->assertUnauthorized();
    }

    public function test_queue_lists_active_jobs_oldest_first(): void
    {
        $this->givenQueuedJob('os-2', '2026-11-04T11:00:00+00:00');
        $this->givenQueuedJob('os-1', '2026-11-04T10:00:00+00:00', ExecutionJob::STATUS_IN_REPAIR);
        $this->givenQueuedJob('os-3', '2026-11-04T09:00:00+00:00', ExecutionJob::STATUS_FINISHED);

        $this->getJson('/api/execution/queue', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.service_order_id', 'os-1')
            ->assertJsonPath('data.1.service_order_id', 'os-2');

        $this->getJson('/api/execution/queue?status=finished', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/execution/queue?status=bogus', $this->authHeaders())->assertUnprocessable();
    }

    public function test_show_and_not_found(): void
    {
        $this->givenQueuedJob();

        $this->getJson('/api/execution/os-1', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('execution.status', 'queued');

        $this->getJson('/api/execution/nao-existe', $this->authHeaders())->assertUnprocessable();
    }

    public function test_diagnosis_repair_finish_notify_os_service(): void
    {
        $this->givenQueuedJob();

        $this->patchJson('/api/execution/os-1/diagnosis', ['notes' => 'barulho na suspensao'], $this->authHeaders())
            ->assertOk()->assertJsonPath('execution.status', 'in_diagnosis');
        $this->patchJson('/api/execution/os-1/repair', [], $this->authHeaders())
            ->assertOk()->assertJsonPath('execution.status', 'in_repair');
        $this->patchJson('/api/execution/os-1/services/s1/start', [], $this->authHeaders())->assertOk();
        $this->patchJson('/api/execution/os-1/services/s1/finish', [], $this->authHeaders())->assertOk();
        $this->patchJson('/api/execution/os-1/finish', [], $this->authHeaders())
            ->assertOk()->assertJsonPath('execution.status', 'finished');

        $replies = $this->repliesToOs();
        $this->assertSame(
            [MessageTypes::EXECUTION_STATUS_CHANGED, MessageTypes::EXECUTION_STATUS_CHANGED, MessageTypes::EXECUTION_FINISHED],
            array_map(fn ($m) => $m->type, $replies)
        );
        $this->assertSame('saga-os-1', $replies[0]->sagaId);
        $this->assertSame('in_diagnosis', $replies[0]->get('status'));
        $this->assertSame('barulho na suspensao', $replies[1]->get('notes'));

        $this->getJson('/api/execution/metrics/average-execution-time', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.service_id', 's1')
            ->assertJsonPath('data.0.executions', 1);
    }

    public function test_invalid_transition_returns_422(): void
    {
        $this->givenQueuedJob();

        $this->patchJson('/api/execution/os-1/finish', [], $this->authHeaders())->assertUnprocessable();
        $this->patchJson('/api/execution/nao-existe/diagnosis', [], $this->authHeaders())->assertUnprocessable();
        $this->assertSame([], $this->repliesToOs());
    }

    public function test_metrics_ignore_untracked_services(): void
    {
        $job = $this->givenQueuedJob('os-5', 'now', ExecutionJob::STATUS_FINISHED);
        $job->services[0]['started_at'] = null;
        $this->jobRepo->update($job);

        $this->getJson('/api/execution/metrics/average-execution-time', $this->authHeaders())
            ->assertOk()->assertJsonPath('data', []);
    }

    public function test_track_service_on_unknown_job(): void
    {
        $this->patchJson('/api/execution/x/services/s1/start', [], $this->authHeaders())->assertUnprocessable();
    }

    public function test_catalog_lookup_resolves_prices(): void
    {
        $service = $this->givenService('Alinhamento', 120);
        $item = $this->givenItem('Pastilha', 10, 80.5);

        $this->postJson('/api/catalog/lookup', [
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('services.0.name', 'Alinhamento')
            ->assertJsonPath('services.0.unit_price', 120)
            ->assertJsonPath('items.0.unit_price', 80.5)
            ->assertJsonPath('items.0.available_stock', 10);
    }

    public function test_catalog_lookup_validation_and_missing(): void
    {
        $this->postJson('/api/catalog/lookup', [], $this->authHeaders())->assertUnprocessable();

        $this->postJson('/api/catalog/lookup', ['services' => [['service_id' => 'x', 'quantity' => 1]]], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('message', "Servico 'x' nao encontrado.");

        $service = $this->givenService('Alinhamento');
        $this->postJson('/api/catalog/lookup', [
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'items' => [['item_id' => 'y', 'quantity' => 1]],
        ], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('message', "Peca 'y' nao encontrada.");
    }
}
