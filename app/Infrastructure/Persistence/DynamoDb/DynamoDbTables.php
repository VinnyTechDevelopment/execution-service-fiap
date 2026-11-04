<?php

namespace App\Infrastructure\Persistence\DynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

/**
 * Acesso comum às tabelas do execution-service: cliente, (un)marshal e
 * scan/query paginados.
 */
class DynamoDbTables
{
    private Marshaler $marshaler;

    /**
     * @param  array<string, string>  $tables  nome lógico => nome físico
     */
    public function __construct(
        public readonly DynamoDbClient $client,
        private array $tables,
    ) {
        $this->marshaler = new Marshaler(['nullify_invalid' => true]);
    }

    public function name(string $logical): string
    {
        return $this->tables[$logical] ?? throw new \InvalidArgumentException("Tabela DynamoDB desconhecida: {$logical}");
    }

    public function marshal(array $item): array
    {
        return $this->marshaler->marshalItem($item);
    }

    public function marshalValue(mixed $value): array
    {
        return $this->marshaler->marshalValue($value);
    }

    public function unmarshal(array $item): array
    {
        return $this->marshaler->unmarshalItem($item);
    }

    public function get(string $table, array $key): ?array
    {
        $result = $this->client->getItem([
            'TableName' => $this->name($table),
            'Key' => $this->marshal($key),
            'ConsistentRead' => true,
        ]);

        $item = $result->get('Item');

        return $item ? $this->unmarshal($item) : null;
    }

    public function put(string $table, array $item, ?string $condition = null): void
    {
        $this->client->putItem(array_filter([
            'TableName' => $this->name($table),
            'Item' => $this->marshal($item),
            'ConditionExpression' => $condition,
        ]));
    }

    public function delete(string $table, array $key): void
    {
        $this->client->deleteItem([
            'TableName' => $this->name($table),
            'Key' => $this->marshal($key),
        ]);
    }

    /**
     * Scan completo (seguindo LastEvaluatedKey). O catálogo da oficina é
     * pequeno; para tabelas grandes use query() com índice.
     *
     * @return array<int, array>
     */
    public function scanAll(string $table, array $params = []): array
    {
        return $this->paginate('scan', $table, $params);
    }

    /**
     * @return array<int, array>
     */
    public function queryAll(string $table, array $params): array
    {
        return $this->paginate('query', $table, $params);
    }

    private function paginate(string $operation, string $table, array $params): array
    {
        $items = [];
        $params['TableName'] = $this->name($table);

        do {
            $result = $this->client->{$operation}($params);

            foreach ($result->get('Items') ?? [] as $item) {
                $items[] = $this->unmarshal($item);
            }

            $params['ExclusiveStartKey'] = $result->get('LastEvaluatedKey');
        } while (! empty($params['ExclusiveStartKey']));

        return $items;
    }
}
