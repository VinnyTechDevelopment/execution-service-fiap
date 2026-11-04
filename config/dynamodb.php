<?php

$prefix = env('DYNAMODB_TABLE_PREFIX', 'local-execution');

return [
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    // LocalStack no docker-compose; vazio em EKS (endpoint real + LabRole).
    'endpoint' => env('AWS_ENDPOINT_URL'),

    // Os mesmos nomes/chaves são criados pelo Terraform (infra/dynamodb.tf)
    // e, localmente, por "php artisan dynamodb:create-tables".
    'tables' => [
        'items' => "{$prefix}-items",
        'services' => "{$prefix}-services",
        'stock_movements' => "{$prefix}-stock-movements",
        'jobs' => "{$prefix}-jobs",
        'processed_messages' => "{$prefix}-processed-messages",
    ],

    // Retenção dos IDs de mensagens processadas (dedupe), via TTL do DynamoDB.
    'processed_messages_ttl_days' => 7,
];
