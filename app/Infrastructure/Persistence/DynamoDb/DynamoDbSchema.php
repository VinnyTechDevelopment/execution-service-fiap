<?php

namespace App\Infrastructure\Persistence\DynamoDb;

/**
 * Definição das tabelas — espelhada em infra/dynamodb.tf (EKS). Usada por
 * "php artisan dynamodb:create-tables" no ambiente local (LocalStack).
 */
final class DynamoDbSchema
{
    /**
     * @return array<string, array> nome lógico => parâmetros de CreateTable (sem TableName)
     */
    public static function definitions(): array
    {
        $string = static fn (string $name) => ['AttributeName' => $name, 'AttributeType' => 'S'];

        return [
            'items' => [
                'AttributeDefinitions' => [$string('id'), $string('code')],
                'KeySchema' => [['AttributeName' => 'id', 'KeyType' => 'HASH']],
                'GlobalSecondaryIndexes' => [[
                    'IndexName' => 'code-index',
                    'KeySchema' => [['AttributeName' => 'code', 'KeyType' => 'HASH']],
                    'Projection' => ['ProjectionType' => 'ALL'],
                ]],
            ],
            'services' => [
                'AttributeDefinitions' => [$string('id')],
                'KeySchema' => [['AttributeName' => 'id', 'KeyType' => 'HASH']],
            ],
            'stock_movements' => [
                'AttributeDefinitions' => [$string('item_id'), $string('created_at_id')],
                'KeySchema' => [
                    ['AttributeName' => 'item_id', 'KeyType' => 'HASH'],
                    ['AttributeName' => 'created_at_id', 'KeyType' => 'RANGE'],
                ],
            ],
            'jobs' => [
                'AttributeDefinitions' => [$string('service_order_id'), $string('status'), $string('queued_at')],
                'KeySchema' => [['AttributeName' => 'service_order_id', 'KeyType' => 'HASH']],
                'GlobalSecondaryIndexes' => [[
                    'IndexName' => 'status-index',
                    'KeySchema' => [
                        ['AttributeName' => 'status', 'KeyType' => 'HASH'],
                        ['AttributeName' => 'queued_at', 'KeyType' => 'RANGE'],
                    ],
                    'Projection' => ['ProjectionType' => 'ALL'],
                ]],
            ],
            'processed_messages' => [
                'AttributeDefinitions' => [$string('message_id')],
                'KeySchema' => [['AttributeName' => 'message_id', 'KeyType' => 'HASH']],
            ],
        ];
    }
}
