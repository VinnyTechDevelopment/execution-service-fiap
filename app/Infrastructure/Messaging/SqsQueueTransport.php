<?php

namespace App\Infrastructure\Messaging;

use Aws\Sqs\SqsClient;

class SqsQueueTransport implements QueueTransportInterface
{
    /** @var array<string, string> */
    private array $urls = [];

    public function __construct(
        private SqsClient $client,
        private QueueNameResolver $names,
    ) {}

    public function send(string $queue, string $body, array $attributes = []): void
    {
        $messageAttributes = [];
        foreach ($attributes as $name => $value) {
            $messageAttributes[$name] = ['DataType' => 'String', 'StringValue' => (string) $value];
        }

        $this->client->sendMessage(array_filter([
            'QueueUrl' => $this->url($queue),
            'MessageBody' => $body,
            'MessageAttributes' => $messageAttributes ?: null,
        ]));
    }

    public function receive(string $queue, int $maxMessages = 10, int $waitSeconds = 20): array
    {
        $result = $this->client->receiveMessage([
            'QueueUrl' => $this->url($queue),
            'MaxNumberOfMessages' => max(1, min(10, $maxMessages)),
            'WaitTimeSeconds' => max(0, min(20, $waitSeconds)),
            'MessageSystemAttributeNames' => ['ApproximateReceiveCount'],
        ]);

        return array_map(
            static fn (array $message) => new ReceivedMessage(
                body: $message['Body'],
                receiptHandle: $message['ReceiptHandle'],
                receiveCount: (int) ($message['Attributes']['ApproximateReceiveCount'] ?? 1),
            ),
            $result->get('Messages') ?? []
        );
    }

    public function delete(string $queue, ReceivedMessage $message): void
    {
        $this->client->deleteMessage([
            'QueueUrl' => $this->url($queue),
            'ReceiptHandle' => $message->receiptHandle,
        ]);
    }

    private function url(string $queue): string
    {
        return $this->urls[$queue] ??= (string) $this->client
            ->getQueueUrl(['QueueName' => $this->names->resolve($queue)])
            ->get('QueueUrl');
    }
}
