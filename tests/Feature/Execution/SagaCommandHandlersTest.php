<?php

namespace Tests\Feature\Execution;

use App\Application\Execution\Services\StockReservationService;
use App\Application\Messaging\Message;
use App\Application\Messaging\MessageTypes;
use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Exceptions\ConcurrentStockUpdateException;
use App\Infrastructure\Messaging\MessageConsumer;
use Tests\Support\UsesInMemoryRepositories;
use Tests\TestCase;

/**
 * Passos da saga executados pelo execution-service, de ponta a ponta:
 * mensagem na fila -> consumer -> handler -> estoque/fila -> resposta.
 */
class SagaCommandHandlersTest extends TestCase
{
    use UsesInMemoryRepositories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindInMemoryRepositories();
    }

    private function sendCommand(string $type, array $payload, string $sagaId = 'saga-1'): Message
    {
        $message = Message::create($type, $sagaId, $payload);
        $this->transport->send(MessageTypes::QUEUE_EXECUTION_COMMANDS, $message->toJson());
        $this->app->make(MessageConsumer::class)->consumeBatch(MessageTypes::QUEUE_EXECUTION_COMMANDS, 10, 0);

        return $message;
    }

    private function reservePayload(string $itemId, float $qty): array
    {
        return [
            'service_order_id' => 'os-1',
            'services' => [['service_id' => 's1', 'name' => 'Revisao', 'quantity' => 1]],
            'items' => [['item_id' => $itemId, 'quantity' => $qty]],
        ];
    }

    public function test_reserve_and_enqueue_happy_path(): void
    {
        $filter = $this->givenItem('Filtro', 5);

        $command = $this->sendCommand(MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE, $this->reservePayload($filter->id, 2));

        $this->assertSame(3.0, $this->itemRepo->findById($filter->id)->stockQuantity);
        $this->assertSame(ExecutionJob::STATUS_QUEUED, $this->jobRepo->findByServiceOrderId('os-1')->status);
        $this->assertCount(1, $this->movementRepo->movements);
        $this->assertSame('os-1', $this->movementRepo->movements[0]->serviceOrderId);

        [$reply] = $this->repliesToOs();
        $this->assertSame(MessageTypes::EXECUTION_QUEUED, $reply->type);
        $this->assertSame('saga-1', $reply->sagaId);
        $this->assertSame($command->correlationId, $reply->correlationId);
        $this->assertSame('execution-service', $reply->source);
    }

    public function test_insufficient_stock_replies_failure_without_side_effects(): void
    {
        $filter = $this->givenItem('Filtro', 1);

        $this->sendCommand(MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE, $this->reservePayload($filter->id, 2));

        $this->assertSame(1.0, $this->itemRepo->findById($filter->id)->stockQuantity);
        $this->assertNull($this->jobRepo->findByServiceOrderId('os-1'));

        [$reply] = $this->repliesToOs();
        $this->assertSame(MessageTypes::EXECUTION_RESERVATION_FAILED, $reply->type);
        $this->assertStringContainsString('Insufficient stock', $reply->get('reason'));
    }

    public function test_unknown_part_replies_failure(): void
    {
        $this->sendCommand(MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE, $this->reservePayload('nao-existe', 1));

        [$reply] = $this->repliesToOs();
        $this->assertSame(MessageTypes::EXECUTION_RESERVATION_FAILED, $reply->type);
        $this->assertStringContainsString('nao encontrada', $reply->get('reason'));
    }

    public function test_redelivered_command_does_not_reserve_twice(): void
    {
        $filter = $this->givenItem('Filtro', 5);
        $payload = $this->reservePayload($filter->id, 2);

        // Mesmo comando com message_id diferente (ex.: reenvio do orquestrador)
        $this->sendCommand(MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE, $payload);
        $this->sendCommand(MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE, $payload);

        $this->assertSame(3.0, $this->itemRepo->findById($filter->id)->stockQuantity);
        $this->assertSame(
            [MessageTypes::EXECUTION_QUEUED, MessageTypes::EXECUTION_QUEUED],
            array_map(fn (Message $m) => $m->type, $this->repliesToOs())
        );
    }

    public function test_cancel_execution_releases_stock(): void
    {
        $filter = $this->givenItem('Filtro', 5);
        $this->sendCommand(MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE, $this->reservePayload($filter->id, 2));

        $this->sendCommand(MessageTypes::EXECUTION_CANCEL, ['service_order_id' => 'os-1', 'reason' => 'cliente desistiu']);

        $this->assertSame(5.0, $this->itemRepo->findById($filter->id)->stockQuantity);
        $this->assertSame(ExecutionJob::STATUS_CANCELLED, $this->jobRepo->findByServiceOrderId('os-1')->status);
        $this->assertSame('entry', $this->movementRepo->movements[1]->movementType->getValue());

        $replies = $this->repliesToOs();
        $this->assertSame(MessageTypes::EXECUTION_CANCELLED, end($replies)->type);
    }

    public function test_cancel_without_job_is_acknowledged(): void
    {
        $this->sendCommand(MessageTypes::EXECUTION_CANCEL, ['service_order_id' => 'os-x']);

        [$reply] = $this->repliesToOs();
        $this->assertSame(MessageTypes::EXECUTION_CANCELLED, $reply->type);
        $this->assertSame([], $reply->get('released'));
    }

    public function test_reservation_retries_on_concurrent_update(): void
    {
        $filter = $this->givenItem('Filtro', 5);
        $this->jobRepo->conflictsToSimulate = 2;

        $this->sendCommand(MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE, $this->reservePayload($filter->id, 1));

        $this->assertSame(4.0, $this->itemRepo->findById($filter->id)->stockQuantity);
    }

    public function test_reservation_gives_up_after_max_attempts(): void
    {
        $filter = $this->givenItem('Filtro', 5);
        $this->jobRepo->conflictsToSimulate = 3;
        $job = ExecutionJob::enqueue('os-9', 'saga', [['service_id' => 's', 'quantity' => 1]], [['item_id' => $filter->id, 'quantity' => 1]], 'now');

        $this->expectException(ConcurrentStockUpdateException::class);
        $this->app->make(StockReservationService::class)->reserveAndEnqueue($job);
    }
}
