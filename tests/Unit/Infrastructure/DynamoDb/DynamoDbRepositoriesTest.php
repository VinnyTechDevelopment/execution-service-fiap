<?php

namespace Tests\Unit\Infrastructure\DynamoDb;

use App\Application\Messaging\Message;
use App\Application\Messaging\ProcessedMessageStoreInterface;
use App\Domain\Execution\Entities\ExecutionJob;
use App\Domain\Execution\Exceptions\ConcurrentStockUpdateException;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use App\Domain\Item\ValueObjects\MovementType;
use App\Domain\Service\Entities\Service;
use App\Infrastructure\Messaging\QueueTransportInterface;
use App\Infrastructure\Messaging\SqsQueueTransport;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbExecutionJobRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbItemRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbProcessedMessageStore;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbSchema;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbServiceRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbStockMovementRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbTables;
use Aws\CommandInterface;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Tests\TestCase;

class DynamoDbRepositoriesTest extends TestCase
{
    private MockHandler $mock;

    /** @var array<int, array{0: string, 1: array}> */
    private array $calls = [];

    private DynamoDbTables $db;

    private Marshaler $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock = new MockHandler;
        $this->m = new Marshaler;
        $client = new DynamoDbClient([
            'region' => 'us-east-1',
            'version' => '2012-08-10',
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler' => $this->mock,
        ]);
        $tables = [];
        foreach (array_keys(DynamoDbSchema::definitions()) as $logical) {
            $tables[$logical] = "t-{$logical}";
        }
        $this->db = new DynamoDbTables($client, $tables);
    }

    /** Enfileira respostas (array => Result; Throwable => erro). */
    private function respond(array|\Throwable ...$responses): void
    {
        foreach ($responses as $response) {
            $this->mock->append(function (CommandInterface $cmd) use ($response) {
                $this->calls[] = [$cmd->getName(), $cmd->toArray()];

                return $response instanceof \Throwable ? $response : new Result($response);
            });
        }
    }

    private function awsError(string $code): DynamoDbException
    {
        return new DynamoDbException($code, $this->createStub(CommandInterface::class), ['code' => $code, 'message' => $code]);
    }

    private function itemRow(array $overrides = []): array
    {
        return $this->m->marshalItem($overrides + [
            'id' => 'i1', 'name' => 'Filtro', 'code' => 'FLT-1', 'type' => 'part', 'description' => null,
            'measure_unit' => 'un', 'stock_quantity' => 5, 'minimum_quantity' => 1, 'unit_price' => 30.5,
            'created_at' => '2026-01-01',
        ]);
    }

    private function newItem(): Item
    {
        return new Item('i1', 'Filtro', new ItemCode('FLT-1'), new ItemType('part'), new MeasureUnit('un'), 5, 1, null, 30.5);
    }

    public function test_tables_name_rejects_unknown_table(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->name('nope');
    }

    public function test_item_repository_crud(): void
    {
        $repo = new DynamoDbItemRepository($this->db);
        $this->respond(
            [],                                     // save -> PutItem
            ['Item' => $this->itemRow()],           // findById
            [],                                     // findById (missing)
            ['Items' => [$this->itemRow()]],        // findByCode -> Query
            ['Items' => []],                        // findByCode miss
            ['Item' => $this->itemRow()],           // update -> GetItem
            [],                                     // update -> PutItem
            [],                                     // delete
        );

        $repo->save($this->newItem());
        $found = $repo->findById('i1');
        $this->assertSame('Filtro', $found->name);
        $this->assertSame(30.5, $found->unitPrice);
        $this->assertNull($repo->findById('nope'));
        $this->assertSame('i1', $repo->findByCode('flt-1')->id);
        $this->assertNull($repo->findByCode('x'));
        $repo->update($this->newItem());
        $repo->delete('i1');

        $this->assertSame('PutItem', $this->calls[0][0]);
        $this->assertSame('t-items', $this->calls[0][1]['TableName']);
        $this->assertSame('code-index', $this->calls[3][1]['IndexName']);
        $this->assertSame('FLT-1', $this->calls[3][1]['ExpressionAttributeValues'][':code']['S']);
        $this->assertSame('2026-01-01', $this->calls[6][1]['Item']['created_at']['S'], 'update preserva created_at');
        $this->assertSame('DeleteItem', $this->calls[7][0]);
    }

    public function test_item_repository_listing_paginates_scan_and_filters(): void
    {
        $repo = new DynamoDbItemRepository($this->db);
        $this->respond(
            ['Items' => [$this->itemRow(['id' => 'b', 'name' => 'Vela'])], 'LastEvaluatedKey' => ['id' => ['S' => 'b']]],
            ['Items' => [$this->itemRow(['id' => 'a', 'name' => 'Amortecedor', 'unit_price' => null])]],
            ['Items' => [$this->itemRow()]],
        );

        $page = $repo->paginate(1, 1, null);
        $this->assertSame(2, $page['total']);
        $this->assertSame('Amortecedor', $page['data'][0]['name'], 'ordenado por nome');
        $this->assertNull($page['data'][0]['unit_price']);
        $this->assertArrayHasKey('ExclusiveStartKey', $this->calls[1][1]);

        $repo->findAll('part');
        $this->assertSame('#type = :type', $this->calls[2][1]['FilterExpression']);
    }

    public function test_service_repository(): void
    {
        $repo = new DynamoDbServiceRepository($this->db);
        $row = fn (string $id, string $name) => $this->m->marshalItem(['id' => $id, 'name' => $name, 'price' => 100]);
        $this->respond(
            [],                                                     // save
            ['Items' => [$row('2', 'Revisao'), $row('1', 'Alinhamento')]], // findAll
            ['Items' => [$row('1', 'Alinhamento')]],               // findByName hit
            ['Items' => []],                                        // findByName miss
            ['Items' => [$row('1', 'A'), $row('2', 'B')]],          // paginate
            ['Item' => $row('1', 'Alinhamento')],                   // findById
            [],                                                     // findById miss
            ['Item' => $row('1', 'A') + ['created_at' => ['S' => 'c']]], // update get
            [],                                                     // update put
            [],                                                     // delete
        );

        $repo->save(Service::create('Alinhamento', 100));
        $this->assertSame('Alinhamento', $repo->findAll()[0]['name']);
        $this->assertSame('1', $repo->findByName('Alinhamento')->id);
        $this->assertNull($repo->findByName('x'));
        $this->assertCount(1, $repo->paginate(2, 1));
        $this->assertSame(100.0, $repo->findById('1')->price);
        $this->assertNull($repo->findById('x'));
        $repo->update(new Service('1', 'A', 120));
        $repo->delete('1');

        $this->assertSame('c', $this->calls[8][1]['Item']['created_at']['S']);
        $this->assertSame('DeleteItem', $this->calls[9][0]);
    }

    public function test_stock_movement_repository(): void
    {
        $repo = new DynamoDbStockMovementRepository($this->db);
        $movement = StockMovement::record('i1', new MovementType('entry'), 2, 0, 2, 'compra', null, 'os-1');
        $this->respond([], ['Items' => [$this->m->marshalItem(DynamoDbStockMovementRepository::toItem($movement, '2026-11-04'))]]);

        $repo->save($movement);
        $result = $repo->findByItemId('i1', 1, 10);

        $this->assertSame(1, $result['total']);
        $this->assertSame('entry', $result['data'][0]['type']);
        $this->assertSame('os-1', $result['data'][0]['service_order_id']);
        $this->assertStringStartsWith($this->calls[0][1]['Item']['created_at']['S'].'#', $this->calls[0][1]['Item']['created_at_id']['S']);
        $this->assertFalse($this->calls[1][1]['ScanIndexForward']);
    }

    private function jobRow(string $id, string $queuedAt, string $status = 'queued'): array
    {
        $job = ExecutionJob::enqueue($id, "saga-{$id}", [['service_id' => 's1', 'name' => 'Rev', 'quantity' => 1]], [['item_id' => 'i1', 'quantity' => 2]], $queuedAt);
        $job->status = $status;

        return $this->m->marshalItem($job->toArray());
    }

    public function test_job_repository_reads_and_update(): void
    {
        $repo = new DynamoDbExecutionJobRepository($this->db);
        $this->respond(
            ['Item' => $this->jobRow('os-1', 't1')],
            [],
            ['Items' => [$this->jobRow('os-2', 't2')]],
            ['Items' => [$this->jobRow('os-1', 't1', 'in_repair')]],
            ['Items' => [$this->jobRow('os-9', 't9', 'finished')]],
            [],
        );

        $job = $repo->findByServiceOrderId('os-1');
        $this->assertSame('saga-os-1', $job->sagaId);
        $this->assertSame(2.0, $job->reservations[0]['quantity']);
        $this->assertNull($repo->findByServiceOrderId('x'));

        $queue = $repo->listByStatuses(['queued', 'in_repair']);
        $this->assertSame(['os-1', 'os-2'], array_map(fn ($j) => $j->serviceOrderId, $queue));
        $this->assertSame('status-index', $this->calls[2][1]['IndexName']);

        $this->assertSame('finished', $repo->listFinished()[0]->status);

        $repo->update($job);
        $this->assertSame('attribute_exists(service_order_id)', $this->calls[5][1]['ConditionExpression']);
    }

    public function test_job_repository_transaction_writes_job_stock_and_movements(): void
    {
        $repo = new DynamoDbExecutionJobRepository($this->db);
        $this->respond([]);

        $job = ExecutionJob::enqueue('os-1', 'saga', [['service_id' => 's', 'quantity' => 1]], [['item_id' => 'i1', 'quantity' => 2]], 't');
        $movement = StockMovement::record('i1', new MovementType('withdrawal'), 2, 5, 3, 'reserva', null, 'os-1');
        $repo->saveWithStockMovements($job, [$movement], isNew: true);

        [$name, $args] = $this->calls[0];
        $this->assertSame('TransactWriteItems', $name);
        $this->assertCount(3, $args['TransactItems']);
        $this->assertSame('attribute_not_exists(service_order_id)', $args['TransactItems'][0]['Put']['ConditionExpression']);
        $update = $args['TransactItems'][1]['Update'];
        $this->assertSame('t-items', $update['TableName']);
        $this->assertSame('5', $update['ExpressionAttributeValues'][':previous']['N']);
        $this->assertSame('3', $update['ExpressionAttributeValues'][':current']['N']);
        $this->assertSame('t-stock_movements', $args['TransactItems'][2]['Put']['TableName']);
    }

    public function test_job_repository_maps_cancelled_transaction_to_domain_exception(): void
    {
        $repo = new DynamoDbExecutionJobRepository($this->db);
        $this->respond($this->awsError('TransactionCanceledException'), $this->awsError('ValidationException'));
        $job = ExecutionJob::enqueue('os-1', 'saga', [['service_id' => 's', 'quantity' => 1]], [], 't');

        try {
            $repo->saveWithStockMovements($job, [], isNew: false);
            $this->fail('deveria lançar ConcurrentStockUpdateException');
        } catch (ConcurrentStockUpdateException) {
            $this->assertSame('attribute_exists(service_order_id)', $this->calls[0][1]['TransactItems'][0]['Put']['ConditionExpression']);
        }

        $this->expectException(DynamoDbException::class);
        $repo->saveWithStockMovements($job, [], isNew: false);
    }

    public function test_processed_message_store(): void
    {
        $store = new DynamoDbProcessedMessageStore($this->db, 7);
        $message = Message::create('t', 's', []);
        $this->respond(
            [], [],                                                   // 1ª: GetItem miss, PutItem ok
            ['Item' => $this->m->marshalItem(['message_id' => 'x'])], // 2ª: já processada
            [], $this->awsError('ConditionalCheckFailedException'),   // 3ª: corrida com outra réplica
            [], $this->awsError('ValidationException'),               // 4ª: erro real
        );

        $calls = 0;
        $run = function () use (&$calls) {
            $calls++;
        };

        $this->assertTrue($store->runOnce($message, $run));
        $this->assertFalse($store->runOnce($message, $run));
        $this->assertTrue($store->runOnce($message, $run));
        $this->assertSame(2, $calls);
        $this->assertSame('attribute_not_exists(message_id)', $this->calls[1][1]['ConditionExpression']);
        $this->assertArrayHasKey('expires_at', $this->calls[1][1]['Item']);

        $this->expectException(DynamoDbException::class);
        $store->runOnce($message, $run);
    }

    public function test_create_tables_command_is_idempotent(): void
    {
        $this->app->instance(DynamoDbTables::class, $this->db);
        $responses = [];
        foreach (array_keys(DynamoDbSchema::definitions()) as $i => $logical) {
            $responses[] = $i === 0 ? $this->awsError('ResourceInUseException') : [];
        }
        $this->respond(...$responses);

        $this->artisan('dynamodb:create-tables')
            ->expectsOutputToContain('Tabela ja existe: t-items')
            ->expectsOutputToContain('Tabela criada: t-jobs')
            ->assertSuccessful();

        $this->assertSame('PAY_PER_REQUEST', $this->calls[1][1]['BillingMode']);
    }

    public function test_create_tables_command_propagates_unexpected_errors(): void
    {
        $this->app->instance(DynamoDbTables::class, $this->db);
        $this->respond($this->awsError('AccessDeniedException'));

        $this->expectException(DynamoDbException::class);
        $this->artisan('dynamodb:create-tables')->run();
    }

    public function test_container_wires_dynamodb_implementations(): void
    {
        $this->assertInstanceOf(DynamoDbItemRepository::class, $this->app->make(ItemRepositoryInterface::class));
        $this->assertInstanceOf(DynamoDbClient::class, $this->app->make(DynamoDbClient::class));
        $this->assertInstanceOf(DynamoDbProcessedMessageStore::class, $this->app->make(ProcessedMessageStoreInterface::class));

        config(['dynamodb.endpoint' => 'http://localstack:4566', 'messaging.sqs.endpoint' => 'http://localstack:4566', 'messaging.driver' => 'sqs']);
        $this->app->forgetInstance(DynamoDbClient::class);
        $this->app->forgetInstance(SqsClient::class);
        $this->app->forgetInstance(QueueTransportInterface::class);
        $this->assertSame('http://localstack:4566', (string) $this->app->make(DynamoDbClient::class)->getEndpoint());
        $this->assertInstanceOf(SqsQueueTransport::class, $this->app->make(QueueTransportInterface::class));
    }
}
