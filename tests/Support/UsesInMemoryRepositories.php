<?php

namespace Tests\Support;

require_once __DIR__.'/InMemoryRepositories.php';

use App\Application\Messaging\Message;
use App\Application\Messaging\ProcessedMessageStoreInterface;
use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Infrastructure\Messaging\InMemoryQueueTransport;
use App\Infrastructure\Messaging\QueueTransportInterface;
use Firebase\JWT\JWT;

trait UsesInMemoryRepositories
{
    protected InMemoryItemRepository $itemRepo;

    protected InMemoryServiceRepository $serviceRepo;

    protected InMemoryStockMovementRepository $movementRepo;

    protected InMemoryExecutionJobRepository $jobRepo;

    protected InMemoryQueueTransport $transport;

    protected function bindInMemoryRepositories(): void
    {
        $this->itemRepo = new InMemoryItemRepository;
        $this->serviceRepo = new InMemoryServiceRepository;
        $this->movementRepo = new InMemoryStockMovementRepository;
        $this->jobRepo = new InMemoryExecutionJobRepository($this->itemRepo, $this->movementRepo);
        $this->transport = new InMemoryQueueTransport;

        $this->app->instance(ItemRepositoryInterface::class, $this->itemRepo);
        $this->app->instance(ServiceRepositoryInterface::class, $this->serviceRepo);
        $this->app->instance(StockMovementRepositoryInterface::class, $this->movementRepo);
        $this->app->instance(ExecutionJobRepositoryInterface::class, $this->jobRepo);
        $this->app->instance(QueueTransportInterface::class, $this->transport);
        $this->app->instance(ProcessedMessageStoreInterface::class, new class implements ProcessedMessageStoreInterface
        {
            private array $seen = [];

            public function runOnce(Message $message, callable $handler): bool
            {
                if (isset($this->seen[$message->id])) {
                    return false;
                }
                $handler();
                $this->seen[$message->id] = true;

                return true;
            }
        });
    }

    protected function givenItem(string $name, float $stock, float $price = 50.0, ?string $code = null): Item
    {
        $item = Item::create(
            name: $name,
            code: new ItemCode($code ?? 'PC-'.strtoupper(substr(md5($name), 0, 6))),
            type: new ItemType('part'),
            measureUnit: new MeasureUnit('un'),
            minimumQuantity: 1,
            description: null,
            unitPrice: $price,
        );
        $item->stockQuantity = $stock;
        $this->itemRepo->save($item);

        return $item;
    }

    protected function givenService(string $name, float $price = 100.0): Service
    {
        $service = Service::create($name, $price);
        $this->serviceRepo->save($service);

        return $service;
    }

    /**
     * @return Message[] mensagens publicadas para o os-service
     */
    protected function repliesToOs(): array
    {
        return array_map(
            static fn (string $body) => Message::fromJson($body),
            $this->transport->pending('os-saga-replies')
        );
    }

    protected function staffToken(string $sub = '7'): string
    {
        return JWT::encode(
            ['sub' => $sub, 'iat' => time(), 'exp' => time() + 3600],
            (string) config('services.staff_jwt.secret'),
            'HS256'
        );
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->staffToken(), 'Accept' => 'application/json'];
    }
}
