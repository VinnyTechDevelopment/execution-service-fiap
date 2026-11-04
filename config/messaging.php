<?php

use App\Application\Execution\Handlers\CancelExecutionHandler;
use App\Application\Execution\Handlers\ReservePartsAndEnqueueHandler;
use App\Application\Messaging\MessageTypes;
use App\Infrastructure\Messaging\DirectMessagePublisher;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbProcessedMessageStore;

return [
    // sqs (EKS / LocalStack) | memory (testes)
    'driver' => env('MESSAGING_DRIVER', 'memory'),

    // Prefixo do ambiente nos nomes físicos das filas (production, homolog, local).
    'queue_prefix' => env('MESSAGING_QUEUE_PREFIX', 'local'),

    'source' => 'execution-service',

    // Fila de entrada consumida por "php artisan messaging:consume".
    'inbound_queue' => MessageTypes::QUEUE_EXECUTION_COMMANDS,

    'sqs' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'endpoint' => env('AWS_ENDPOINT_URL'),
    ],

    // Sem banco relacional aqui: publica direto no SQS. A idempotência dos
    // handlers (ver Application/Execution/Handlers) cobre reentregas.
    'publisher' => DirectMessagePublisher::class,

    'processed_store' => DynamoDbProcessedMessageStore::class,

    'handlers' => [
        MessageTypes::EXECUTION_RESERVE_AND_ENQUEUE => ReservePartsAndEnqueueHandler::class,
        MessageTypes::EXECUTION_CANCEL => CancelExecutionHandler::class,
    ],
];
