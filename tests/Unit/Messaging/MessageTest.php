<?php

namespace Tests\Unit\Messaging;

use App\Application\Messaging\Message;
use Tests\TestCase;

class MessageTest extends TestCase
{
    public function test_create_generates_id_and_uses_saga_as_default_correlation(): void
    {
        $message = Message::create('billing.CreateQuote', 'saga-1', ['total' => 10.5]);

        $this->assertNotEmpty($message->id);
        $this->assertSame('saga-1', $message->sagaId);
        $this->assertSame('saga-1', $message->correlationId);
        $this->assertSame(10.5, $message->get('total'));
        $this->assertNull($message->get('missing'));
        $this->assertSame('x', $message->get('missing', 'x'));
    }

    public function test_reply_keeps_saga_and_correlation(): void
    {
        $command = Message::create('billing.CreateQuote', 'saga-1', [], 'corr-9');
        $reply = $command->reply('billing.QuoteCreated', ['quote_id' => 'q1']);

        $this->assertNotSame($command->id, $reply->id);
        $this->assertSame('saga-1', $reply->sagaId);
        $this->assertSame('corr-9', $reply->correlationId);
        $this->assertSame('billing.QuoteCreated', $reply->type);
    }

    public function test_json_round_trip_preserves_all_fields(): void
    {
        $original = Message::create('t', 's', ['a' => ['b' => 1]])
            ->withSource('os-service')
            ->withTraceHeaders(['traceparent' => '00-abc']);

        $copy = Message::fromJson($original->toJson());

        $this->assertEquals($original->toArray(), $copy->toArray());
        $this->assertSame(1, $copy->get('a.b'));
    }

    public function test_from_json_rejects_malformed_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Message::fromJson('{not json');
    }

    public function test_from_json_rejects_non_object(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Message::fromJson('"just a string"');
    }

    public function test_from_array_requires_mandatory_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'saga_id'");
        Message::fromArray(['message_id' => '1', 'type' => 't', 'payload' => []]);
    }

    public function test_from_array_requires_payload_object(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Message::fromArray(['message_id' => '1', 'type' => 't', 'saga_id' => 's', 'payload' => 'x']);
    }

    public function test_from_array_fills_optional_fields(): void
    {
        $message = Message::fromArray(['message_id' => '1', 'type' => 't', 'saga_id' => 's', 'payload' => [], 'trace_headers' => 'bad']);

        $this->assertSame('s', $message->correlationId);
        $this->assertSame([], $message->traceHeaders);
        $this->assertSame('', $message->source);
    }

    public function test_constructor_rejects_empty_type_or_saga(): void
    {
        try {
            new Message('1', '', 's', 'c', [], 'now');
            $this->fail('Tipo vazio deveria falhar');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(\InvalidArgumentException::class);
        new Message('1', 't', '', 'c', [], 'now');
    }
}
