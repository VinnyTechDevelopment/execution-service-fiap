<?php

namespace App\Infrastructure\Messaging;

/**
 * Transporte em memória (driver "memory"): usado nos testes e para rodar um
 * serviço isolado sem SQS/LocalStack.
 */
class InMemoryQueueTransport implements QueueTransportInterface
{
    /** @var array<string, array<int, array{body: string, attributes: array}>> */
    private array $queues = [];

    private int $sequence = 0;

    public function send(string $queue, string $body, array $attributes = []): void
    {
        $this->queues[$queue][++$this->sequence] = ['body' => $body, 'attributes' => $attributes];
    }

    public function receive(string $queue, int $maxMessages = 10, int $waitSeconds = 20): array
    {
        $messages = array_slice($this->queues[$queue] ?? [], 0, $maxMessages, true);

        $received = [];
        foreach ($messages as $handle => $message) {
            $received[] = new ReceivedMessage($message['body'], (string) $handle);
        }

        return $received;
    }

    public function delete(string $queue, ReceivedMessage $message): void
    {
        unset($this->queues[$queue][(int) $message->receiptHandle]);
    }

    /**
     * @return string[] corpos das mensagens pendentes na fila
     */
    public function pending(string $queue): array
    {
        return array_values(array_map(static fn (array $m) => $m['body'], $this->queues[$queue] ?? []));
    }
}
