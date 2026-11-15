<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\DynamoDb\DynamoDbSchema;
use App\Infrastructure\Persistence\DynamoDb\DynamoDbTables;
use Aws\DynamoDb\Exception\DynamoDbException;
use Illuminate\Console\Command;

/**
 * Só para desenvolvimento local (LocalStack). Em EKS as tabelas são criadas
 * pelo Terraform (infra/dynamodb.tf).
 */
class CreateDynamoDbTablesCommand extends Command
{
    protected $signature = 'dynamodb:create-tables';

    protected $description = 'Cria as tabelas DynamoDB do execution-service (ambiente local / LocalStack)';

    public function handle(DynamoDbTables $db): int
    {
        foreach (DynamoDbSchema::definitions() as $logical => $definition) {
            $name = $db->name($logical);

            try {
                $db->client->createTable(['TableName' => $name, 'BillingMode' => 'PAY_PER_REQUEST'] + $definition);
                $this->info("Tabela criada: {$name}");
            } catch (DynamoDbException $e) {
                if ($e->getAwsErrorCode() !== 'ResourceInUseException') {
                    throw $e;
                }

                $this->line("Tabela ja existe: {$name}");
            }
        }

        return self::SUCCESS;
    }
}
