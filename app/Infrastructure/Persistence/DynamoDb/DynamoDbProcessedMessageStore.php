<?php

namespace App\Infrastructure\Persistence\DynamoDb;

use App\Application\Messaging\Message;
use App\Application\Messaging\ProcessedMessageStoreInterface;
use Aws\DynamoDb\Exception\DynamoDbException;

/**
 * Inbox idempotente no DynamoDB: o message_id só é registrado depois que o
 * handler terminou com sucesso. Se o handler falhar no meio (ex.: estoque
 * reservado mas a resposta não foi publicada), a reentrega roda o handler de
 * novo — por isso os handlers deste serviço também são idempotentes.
 * Os registros expiram via TTL (expires_at).
 */
class DynamoDbProcessedMessageStore implements ProcessedMessageStoreInterface
{
    public function __construct(
        private DynamoDbTables $db,
        private int $ttlDays = 7,
    ) {}

    public function runOnce(Message $message, callable $handler): bool
    {
        if ($this->db->get('processed_messages', ['message_id' => $message->id]) !== null) {
            return false;
        }

        $handler();

        try {
            $this->db->put('processed_messages', [
                'message_id' => $message->id,
                'type' => $message->type,
                'saga_id' => $message->sagaId,
                'processed_at' => now()->toIso8601String(),
                'expires_at' => now()->addDays($this->ttlDays)->getTimestamp(),
            ], 'attribute_not_exists(message_id)');
        } catch (DynamoDbException $e) {
            // Outra réplica registrou a mesma mensagem em paralelo — os
            // handlers idempotentes já cobriram esse caso.
            if ($e->getAwsErrorCode() !== 'ConditionalCheckFailedException') {
                throw $e;
            }
        }

        return true;
    }
}
