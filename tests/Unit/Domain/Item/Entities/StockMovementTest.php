<?php

namespace Tests\Unit\Domain\Item\Entities;

use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\ValueObjects\MovementType;
use PHPUnit\Framework\TestCase;

class StockMovementTest extends TestCase
{
    private string $itemId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    public function test_record_creates_movement_with_all_fields(): void
    {
        $type = new MovementType(MovementType::ENTRY);

        $movement = StockMovement::record(
            itemId: $this->itemId,
            movementType: $type,
            quantity: 10.0,
            previousQuantity: 0.0,
            currentQuantity: 10.0,
            reason: 'Purchase NF 001',
            notes: 'Supplier X'
        );

        $this->assertNotEmpty($movement->id);
        $this->assertSame($this->itemId, $movement->itemId);
        $this->assertSame(MovementType::ENTRY, $movement->movementType->getValue());
        $this->assertSame(10.0, $movement->quantity);
        $this->assertSame(0.0, $movement->previousQuantity);
        $this->assertSame(10.0, $movement->currentQuantity);
        $this->assertSame('Purchase NF 001', $movement->reason);
        $this->assertSame('Supplier X', $movement->notes);
    }

    public function test_record_accepts_null_notes(): void
    {
        $movement = StockMovement::record(
            itemId: $this->itemId,
            movementType: new MovementType(MovementType::WITHDRAWAL),
            quantity: 3.0,
            previousQuantity: 10.0,
            currentQuantity: 7.0,
            reason: 'Work order',
            notes: null
        );

        $this->assertNull($movement->notes);
    }

    public function test_record_generates_uuid(): void
    {
        $movement = StockMovement::record(
            itemId: $this->itemId,
            movementType: new MovementType(MovementType::ENTRY),
            quantity: 1.0,
            previousQuantity: 0.0,
            currentQuantity: 1.0,
            reason: 'Test',
            notes: null
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $movement->id
        );
    }

    public function test_record_generates_unique_ids(): void
    {
        $make = fn () => StockMovement::record(
            itemId: $this->itemId,
            movementType: new MovementType(MovementType::ENTRY),
            quantity: 1.0,
            previousQuantity: 0.0,
            currentQuantity: 1.0,
            reason: 'Test',
            notes: null
        );

        $this->assertNotSame($make()->id, $make()->id);
    }
}
