<?php

namespace Tests\Unit\Messaging;

use App\Infrastructure\Messaging\InMemoryQueueTransport;
use App\Infrastructure\Messaging\QueueNameResolver;
use App\Infrastructure\Messaging\ReceivedMessage;
use App\Infrastructure\Messaging\SqsQueueTransport;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Tests\TestCase;

class TransportTest extends TestCase
{
    public function test_queue_name_resolver_applies_prefix(): void
    {
        $this->assertSame('production-billing-commands', (new QueueNameResolver('production'))->resolve('billing-commands'));
        $this->assertSame('billing-commands', (new QueueNameResolver(''))->resolve('billing-commands'));
    }

    public function test_in_memory_transport_send_receive_delete(): void
    {
        $transport = new InMemoryQueueTransport;
        $transport->send('q', 'a');
        $transport->send('q', 'b');

        $received = $transport->receive('q', 1);
        $this->assertCount(1, $received);
        $this->assertSame('a', $received[0]->body);

        $transport->delete('q', $received[0]);
        $this->assertSame(['b'], $transport->pending('q'));
        $this->assertSame([], $transport->pending('other'));
    }

    public function test_sqs_transport_resolves_url_once_and_maps_calls(): void
    {
        $calls = [];
        $mock = new MockHandler;
        $record = function (CommandInterface $cmd) use (&$calls) {
            $calls[] = [$cmd->getName(), $cmd->toArray()];
        };

        $mock->append(function (CommandInterface $cmd) use ($record) {
            $record($cmd);

            return new Result(['QueueUrl' => 'http://sqs/local-q']);
        });
        $mock->append(function (CommandInterface $cmd) use ($record) {
            $record($cmd);

            return new Result(['MessageId' => 'm1']);
        });
        $mock->append(function (CommandInterface $cmd) use ($record) {
            $record($cmd);

            return new Result(['Messages' => [
                ['Body' => '{"x":1}', 'ReceiptHandle' => 'rh-1', 'Attributes' => ['ApproximateReceiveCount' => '3']],
            ]]);
        });
        $mock->append(function (CommandInterface $cmd) use ($record) {
            $record($cmd);

            return new Result([]);
        });

        $client = new SqsClient([
            'region' => 'us-east-1',
            'version' => '2012-11-05',
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler' => $mock,
        ]);

        $transport = new SqsQueueTransport($client, new QueueNameResolver('local'));
        $transport->send('q', 'body', ['type' => 'T']);
        $received = $transport->receive('q', 50, 99);
        $transport->delete('q', $received[0]);

        $this->assertSame('GetQueueUrl', $calls[0][0]);
        $this->assertSame('local-q', $calls[0][1]['QueueName']);
        $this->assertSame('SendMessage', $calls[1][0]);
        $this->assertSame('http://sqs/local-q', $calls[1][1]['QueueUrl']);
        $this->assertSame('T', $calls[1][1]['MessageAttributes']['type']['StringValue']);
        $this->assertSame('ReceiveMessage', $calls[2][0]);
        $this->assertSame(10, $calls[2][1]['MaxNumberOfMessages']);
        $this->assertSame(20, $calls[2][1]['WaitTimeSeconds']);
        $this->assertSame('DeleteMessage', $calls[3][0]);
        $this->assertSame('rh-1', $calls[3][1]['ReceiptHandle']);

        $this->assertInstanceOf(ReceivedMessage::class, $received[0]);
        $this->assertSame(3, $received[0]->receiveCount);
        $this->assertCount(4, $calls, 'GetQueueUrl deve ser chamado uma única vez (cache)');
    }

    public function test_sqs_transport_handles_empty_receive(): void
    {
        $mock = new MockHandler([new Result(['QueueUrl' => 'u']), new Result([])]);
        $client = new SqsClient([
            'region' => 'us-east-1', 'version' => '2012-11-05',
            'credentials' => ['key' => 'k', 'secret' => 's'], 'handler' => $mock,
        ]);

        $this->assertSame([], (new SqsQueueTransport($client, new QueueNameResolver('')))->receive('q'));
    }
}
