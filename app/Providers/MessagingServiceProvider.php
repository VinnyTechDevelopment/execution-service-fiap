<?php

namespace App\Providers;

use App\Application\Messaging\MessagePublisherInterface;
use App\Application\Messaging\ProcessedMessageStoreInterface;
use App\Infrastructure\Messaging\DirectMessagePublisher;
use App\Infrastructure\Messaging\InMemoryQueueTransport;
use App\Infrastructure\Messaging\MessageDispatcher;
use App\Infrastructure\Messaging\QueueNameResolver;
use App\Infrastructure\Messaging\QueueTransportInterface;
use App\Infrastructure\Messaging\SqsQueueTransport;
use App\Infrastructure\Observability\Tracer;
use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tracer::class);

        $this->app->singleton(QueueNameResolver::class, fn () => new QueueNameResolver(
            (string) config('messaging.queue_prefix', '')
        ));

        $this->app->singleton(SqsClient::class, function () {
            $config = [
                'region' => config('messaging.sqs.region'),
                'version' => '2012-11-05',
            ];

            if ($endpoint = config('messaging.sqs.endpoint')) {
                // LocalStack (docker-compose). Em EKS fica vazio e o SDK usa
                // o endpoint real + credenciais da LabRole do node (IMDS).
                $config['endpoint'] = $endpoint;
                $config['credentials'] = ['key' => 'test', 'secret' => 'test'];
            }

            return new SqsClient($config);
        });

        $this->app->singleton(QueueTransportInterface::class, function (Application $app) {
            return match (config('messaging.driver')) {
                'sqs' => new SqsQueueTransport($app->make(SqsClient::class), $app->make(QueueNameResolver::class)),
                default => new InMemoryQueueTransport,
            };
        });

        $this->app->singleton(DirectMessagePublisher::class, fn (Application $app) => new DirectMessagePublisher(
            $app->make(QueueTransportInterface::class),
            $app->make(Tracer::class),
            (string) config('messaging.source'),
        ));

        $this->app->bind(MessagePublisherInterface::class, fn (Application $app) => $app->make(
            config('messaging.publisher', DirectMessagePublisher::class)
        ));

        $this->app->bind(ProcessedMessageStoreInterface::class, fn (Application $app) => $app->make(
            config('messaging.processed_store')
        ));

        $this->app->bind(MessageDispatcher::class, fn (Application $app) => new MessageDispatcher(
            $app,
            $app->make(ProcessedMessageStoreInterface::class),
            $app->make(Tracer::class),
            (array) config('messaging.handlers', []),
        ));
    }
}
