<?php

namespace App\Providers;

use App\Domain\Execution\Interfaces\ExecutionJobRepositoryInterface;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbExecutionJobRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbItemRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbProcessedMessageStore;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbServiceRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbStockMovementRepository;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbTables;
use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DynamoDbClient::class, function () {
            $config = [
                'region' => config('dynamodb.region'),
                'version' => '2012-08-10',
            ];

            if ($endpoint = config('dynamodb.endpoint')) {
                // LocalStack (docker-compose). Em EKS: endpoint real + LabRole do node.
                $config['endpoint'] = $endpoint;
                $config['credentials'] = ['key' => 'test', 'secret' => 'test'];
            }

            return new DynamoDbClient($config);
        });

        $this->app->singleton(DynamoDbTables::class, fn (Application $app) => new DynamoDbTables(
            $app->make(DynamoDbClient::class),
            (array) config('dynamodb.tables'),
        ));

        $this->app->bind(DynamoDbProcessedMessageStore::class, fn (Application $app) => new DynamoDbProcessedMessageStore(
            $app->make(DynamoDbTables::class),
            (int) config('dynamodb.processed_messages_ttl_days', 7),
        ));

        $this->app->bind(ItemRepositoryInterface::class, DynamoDbItemRepository::class);
        $this->app->bind(StockMovementRepositoryInterface::class, DynamoDbStockMovementRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, DynamoDbServiceRepository::class);
        $this->app->bind(ExecutionJobRepositoryInterface::class, DynamoDbExecutionJobRepository::class);
    }
}
