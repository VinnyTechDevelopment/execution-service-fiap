<?php

namespace Tests\Unit\Domain\Item\Entities;

use App\Domain\Item\Entities\Item;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    private function makeItem(float $stock = 0.0, float $minimum = 5.0): Item
    {
        $item = Item::create(
            name: 'Test Item',
            code: new ItemCode('ITEM-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit(MeasureUnit::UNIT),
            minimumQuantity: $minimum,
            description: null,
            unitPrice: null
        );

        // Bypass encapsulation to set initial stock for tests that need it
        if ($stock > 0) {
            $item->addStock($stock);
        }

        return $item;
    }

    public function test_create_generates_uuid_and_starts_with_zero_stock(): void
    {
        $item = Item::create(
            name: 'Brake Pad',
            code: new ItemCode('BP-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit(MeasureUnit::UNIT),
            minimumQuantity: 2.0,
            description: 'Front brake pad',
            unitPrice: 49.90
        );

        $this->assertNotEmpty($item->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $item->id
        );
        $this->assertSame(0.0, $item->stockQuantity);
        $this->assertSame('Brake Pad', $item->name);
        $this->assertSame('BP-001', $item->code->getValue());
        $this->assertSame(2.0, $item->minimumQuantity);
        $this->assertSame('Front brake pad', $item->description);
        $this->assertSame(49.90, $item->unitPrice);
    }

    public function test_create_generates_unique_ids(): void
    {
        $a = $this->makeItem();
        $b = $this->makeItem();

        $this->assertNotSame($a->id, $b->id);
    }

    public function test_add_stock_increases_quantity(): void
    {
        $item = $this->makeItem();

        $item->addStock(10.0);

        $this->assertSame(10.0, $item->stockQuantity);
    }

    public function test_add_stock_accumulates_multiple_entries(): void
    {
        $item = $this->makeItem();

        $item->addStock(10.0);
        $item->addStock(5.0);

        $this->assertSame(15.0, $item->stockQuantity);
    }

    public function test_add_stock_throws_for_zero_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock entry quantity must be greater than zero.');

        $this->makeItem()->addStock(0.0);
    }

    public function test_add_stock_throws_for_negative_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeItem()->addStock(-1.0);
    }

    public function test_remove_stock_decreases_quantity(): void
    {
        $item = $this->makeItem(stock: 20.0);

        $item->removeStock(8.0);

        $this->assertSame(12.0, $item->stockQuantity);
    }

    public function test_remove_stock_allows_exact_quantity(): void
    {
        $item = $this->makeItem(stock: 10.0);

        $item->removeStock(10.0);

        $this->assertSame(0.0, $item->stockQuantity);
    }

    public function test_remove_stock_throws_for_zero_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock withdrawal quantity must be greater than zero.');

        $this->makeItem(stock: 10.0)->removeStock(0.0);
    }

    public function test_remove_stock_throws_for_negative_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeItem(stock: 10.0)->removeStock(-5.0);
    }

    public function test_remove_stock_throws_domain_exception_when_insufficient(): void
    {
        $item = $this->makeItem(stock: 5.0);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock. Available: 5, requested: 6.');

        $item->removeStock(6.0);
    }

    public function test_is_low_stock_returns_true_when_stock_equals_minimum(): void
    {
        $item = $this->makeItem(stock: 5.0, minimum: 5.0);

        $this->assertTrue($item->isLowStock());
    }

    public function test_is_low_stock_returns_true_when_stock_below_minimum(): void
    {
        $item = $this->makeItem(stock: 3.0, minimum: 5.0);

        $this->assertTrue($item->isLowStock());
    }

    public function test_is_low_stock_returns_false_when_stock_above_minimum(): void
    {
        $item = $this->makeItem(stock: 6.0, minimum: 5.0);

        $this->assertFalse($item->isLowStock());
    }

    public function test_update_data_replaces_only_non_null_fields(): void
    {
        $item = $this->makeItem();
        $originalCode = $item->code;
        $originalType = $item->type;

        $item->updateData(
            name: 'Updated Name',
            code: null,
            type: null,
            measureUnit: new MeasureUnit(MeasureUnit::KILOGRAM),
            minimumQuantity: null,
            description: 'New description',
            unitPrice: null
        );

        $this->assertSame('Updated Name', $item->name);
        $this->assertSame($originalCode, $item->code);
        $this->assertSame($originalType, $item->type);
        $this->assertSame('kg', $item->measureUnit->getValue());
        $this->assertSame('New description', $item->description);
    }

    public function test_update_data_with_all_nulls_changes_nothing(): void
    {
        $item = $this->makeItem();
        $originalName = $item->name;
        $originalMinimum = $item->minimumQuantity;

        $item->updateData(null, null, null, null, null, null, null);

        $this->assertSame($originalName, $item->name);
        $this->assertSame($originalMinimum, $item->minimumQuantity);
    }
}
