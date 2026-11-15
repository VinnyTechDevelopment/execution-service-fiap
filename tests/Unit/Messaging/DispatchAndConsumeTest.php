<?php

namespace Tests\Unit\Messaging;

use App\Application\Messaging\Message;
use App\Application\Messaging\MessageHandlerInterface;
use App\Application\Messaging\ProcessedMessageStoreInterface;
use App\Infrastructure\Messaging\DirectMessagePublisher;
use App\Infrastructure\Messaging\InMemoryQueueTransport;
use App\Infrastructure\Messaging\MessageConsumer;
use App\Infrastructure\Messaging\MessageDispatcher;
use App\Infrastructure\Observability\Tracer;
use Tests\TestCase;

class RecordingHandler implements MessageHandlerInterface
{
    public static array $handled = [];

    public static bool $fail = false;

    public function handle(Message $message): void
    {
        if (self::$fail) {
            throw new \RuntimeException('boom');
        }

        self::$handled[] = $message->id;
    }
}

class InMemoryProcessedStore implements ProcessedMessageStoreInterface
{
    public array $seen = [];

    public function runOnce(Message $message, callable $handler): bool
    {
        if (isset($this->seen[$message->id])) {
            return false;
        }

        $handler();
        $this->seen[$message->id] = true;

        return true;
    }
}

class DispatchAndConsumeTest extends TestCase
{
    private InMemoryQueueTransport $transport;

    private InMemoryProcessedStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        RecordingHandler::$handled = [];
        RecordingHandler::$fail = false;
        $this->transport = new InMemoryQueueTransport;
        $this->store = new InMemoryProcessedStore;
    }

    private function consumer(): MessageConsumer
    {
        $tracer = new Tracer(false);
        $dispatcher = new MessageDispatcher($this->app, $this->store, $tracer, ['test.Known' => RecordingHandler::class]);

        return new MessageConsumer($this->transport, $dispatcher, $tracer);
    }

    public function test_direct_publisher_adds_source_and_sends(): void
    {
        $publisher = new DirectMessagePublisher($this->transport, new Tracer(false), 'svc-a');
        $publisher->publish('q', Message::create('test.Known', 'saga', ['k' => 'v']));

        $sent = Message::fromJson($this->transport->pending('q')[0]);
        $this->assertSame('svc-a', $sent->source);
        $this->assertSame('v', $sent->get('k'));
    }

    public function test_prepare_keeps_existing_source(): void
    {
        $publisher = new DirectMessagePublisher($this->transport, new Tracer(false), 'svc-a');
        $prepared = $publisher->prepare(Message::create('t', 's', [])->withSource('other')->withTraceHeaders(['h' => '1']));

        $this->assertSame('other', $prepared->source);
        $this->assertSame(['h' => '1'], $prepared->traceHeaders);
    }

    public function test_consumer_dispatches_and_deletes_message(): void
    {
        $message = Message::create('test.Known', 'saga', []);
        $this->transport->send('q', $message->toJson());

        $this->assertSame(1, $this->consumer()->consumeBatch('q', 10, 0));
        $this->assertSame([$message->id], RecordingHandler::$handled);
        $this->assertSame([], $this->transport->pending('q'));
    }

    public function test_duplicate_message_is_handled_only_once(): void
    {
        $message = Message::create('test.Known', 'saga', []);
        $this->transport->send('q', $message->toJson());
        $this->transport->send('q', $message->toJson());

        $this->consumer()->consumeBatch('q', 10, 0);

        $this->assertCount(1, RecordingHandler::$handled);
        $this->assertSame([], $this->transport->pending('q'));
    }

    public function test_unknown_type_is_acknowledged_without_handler(): void
    {
        $this->transport->send('q', Message::create('test.Unknown', 'saga', [])->toJson());

        $this->assertSame(1, $this->consumer()->consumeBatch('q', 10, 0));
        $this->assertSame([], RecordingHandler::$handled);
    }

    public function test_invalid_message_is_dropped(): void
    {
        $this->transport->send('q', 'garbage');

        $this->assertSame(0, $this->consumer()->consumeBatch('q', 10, 0));
        $this->assertSame([], $this->transport->pending('q'));
    }

    public function test_failed_handler_keeps_message_for_retry(): void
    {
        RecordingHandler::$fail = true;
        $this->transport->send('q', Message::create('test.Known', 'saga', [])->toJson());

        $this->assertSame(0, $this->consumer()->consumeBatch('q', 10, 0));
        $this->assertCount(1, $this->transport->pending('q'));
    }

    public function test_consume_command_runs_one_batch(): void
    {
        $this->app->instance(MessageConsumer::class, $this->consumer());
        $this->transport->send('cmd-q', Message::create('test.Known', 'saga', [])->toJson());

        $this->artisan('messaging:consume', ['--queue' => 'cmd-q', '--once' => true, '--wait' => 0])
            ->expectsOutputToContain('1 mensagem(ns) processada(s).')
            ->assertSuccessful();
    }

    public function test_disabled_tracer_is_a_no_op(): void
    {
        $tracer = new Tracer(false);

        $this->assertFalse($tracer->enabled());
        $this->assertSame([], $tracer->outgoingHeaders());
        $tracer->acceptHeaders(['a' => 'b']);
        $tracer->startBackgroundTransaction('x');
        $tracer->addAttributes(['a' => 1]);
        $tracer->recordEvent('E', ['a' => 1]);
        $tracer->noticeError(new \RuntimeException('x'));
        $tracer->endTransaction();
        $this->assertFalse((new Tracer)->enabled() && ! extension_loaded('newrelic'));
    }
}
