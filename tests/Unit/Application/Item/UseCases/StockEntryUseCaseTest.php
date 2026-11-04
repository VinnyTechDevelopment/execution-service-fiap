<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\DTOs\StockEntryDTO;
use App\Application\Item\UseCases\StockEntryUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use App\Domain\Item\ValueObjects\MovementType;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class StockEntryUseCaseTest extends TestCase
{
    private MockInterface $itemRepository;

    private MockInterface $movementRepository;

    private StockEntryUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $this->movementRepository = Mockery::mock(StockMovementRepositoryInterface::class);
        $this->useCase = new StockEntryUseCase($this->itemRepository, $this->movementRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeItem(float $stock = 0.0): Item
    {
        return new Item(
            id: 'uuid-1111',
            name: 'Test Item',
            code: new ItemCode('ITEM-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit(MeasureUnit::UNIT),
            stockQuantity: $stock,
            minimumQuantity: 2.0,
            description: null,
            unitPrice: null
        );
    }

    public function test_records_entry_and_updates_item_stock(): void
    {
        $item = $this->makeItem(stock: 5.0);
        $dto = new StockEntryDTO(
            itemId: 'uuid-1111',
            quantity: 10.0,
            reason: 'Purchase NF 001',
            notes: null
        );

        $this->itemRepository->shouldReceive('findById')->once()->with('uuid-1111')->andReturn($item);
        $this->itemRepository->shouldReceive('update')->once()->with($item);
        $this->movementRepository->shouldReceive('save')->once()->with(Mockery::type(StockMovement::class));

        $movement = $this->useCase->execute($dto);

        $this->assertInstanceOf(StockMovement::class, $movement);
        $this->assertSame(MovementType::ENTRY, $movement->movementType->getValue());
        $this->assertSame(10.0, $movement->quantity);
        $this->assertSame(5.0, $movement->previousQuantity);
        $this->assertSame(15.0, $movement->currentQuantity);
        $this->assertSame(15.0, $item->stockQuantity);
    }

    public function test_throws_when_item_not_found(): void
    {
        $this->itemRepository->shouldReceive('findById')->once()->andReturnNull();
        $this->itemRepository->shouldNotReceive('update');
        $this->movementRepository->shouldNotReceive('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item not found.');

        $this->useCase->execute(new StockEntryDTO('uuid-9999', 10.0, 'Test', null));
    }

    public function test_captures_previous_quantity_before_adding_stock(): void
    {
        $item = $this->makeItem(stock: 3.0);
        $dto = new StockEntryDTO('uuid-1111', 7.0, 'Replenishment', null);

        $this->itemRepository->shouldReceive('findById')->andReturn($item);
        $this->itemRepository->shouldReceive('update');
        $this->movementRepository->shouldReceive('save');

        $movement = $this->useCase->execute($dto);

        $this->assertSame(3.0, $movement->previousQuantity);
        $this->assertSame(10.0, $movement->currentQuantity);
    }
}
