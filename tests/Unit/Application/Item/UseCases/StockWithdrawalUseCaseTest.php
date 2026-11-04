<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\DTOs\StockWithdrawalDTO;
use App\Application\Item\UseCases\StockWithdrawalUseCase;
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

class StockWithdrawalUseCaseTest extends TestCase
{
    private MockInterface $itemRepository;

    private MockInterface $movementRepository;

    private StockWithdrawalUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $this->movementRepository = Mockery::mock(StockMovementRepositoryInterface::class);
        $this->useCase = new StockWithdrawalUseCase($this->itemRepository, $this->movementRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeItem(float $stock = 20.0): Item
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

    public function test_records_withdrawal_and_updates_item_stock(): void
    {
        $item = $this->makeItem(stock: 20.0);
        $dto = new StockWithdrawalDTO(
            itemId: 'uuid-1111',
            quantity: 5.0,
            reason: 'Work order WO-001',
            notes: null
        );

        $this->itemRepository->shouldReceive('findById')->once()->with('uuid-1111')->andReturn($item);
        $this->itemRepository->shouldReceive('update')->once()->with($item);
        $this->movementRepository->shouldReceive('save')->once()->with(Mockery::type(StockMovement::class));

        $movement = $this->useCase->execute($dto);

        $this->assertInstanceOf(StockMovement::class, $movement);
        $this->assertSame(MovementType::WITHDRAWAL, $movement->movementType->getValue());
        $this->assertSame(5.0, $movement->quantity);
        $this->assertSame(20.0, $movement->previousQuantity);
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

        $this->useCase->execute(new StockWithdrawalDTO('uuid-9999', 5.0, 'Test', null));
    }

    public function test_throws_domain_exception_when_insufficient_stock(): void
    {
        $item = $this->makeItem(stock: 3.0);

        $this->itemRepository->shouldReceive('findById')->once()->andReturn($item);
        $this->itemRepository->shouldNotReceive('update');
        $this->movementRepository->shouldNotReceive('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock.');

        $this->useCase->execute(new StockWithdrawalDTO('uuid-1111', 10.0, 'Test', null));
    }

    public function test_captures_previous_quantity_before_removing_stock(): void
    {
        $item = $this->makeItem(stock: 12.0);
        $dto = new StockWithdrawalDTO('uuid-1111', 4.0, 'Maintenance', null);

        $this->itemRepository->shouldReceive('findById')->andReturn($item);
        $this->itemRepository->shouldReceive('update');
        $this->movementRepository->shouldReceive('save');

        $movement = $this->useCase->execute($dto);

        $this->assertSame(12.0, $movement->previousQuantity);
        $this->assertSame(8.0, $movement->currentQuantity);
    }

    public function test_allows_exact_stock_withdrawal(): void
    {
        $item = $this->makeItem(stock: 5.0);
        $dto = new StockWithdrawalDTO('uuid-1111', 5.0, 'Last unit', null);

        $this->itemRepository->shouldReceive('findById')->andReturn($item);
        $this->itemRepository->shouldReceive('update');
        $this->movementRepository->shouldReceive('save');

        $movement = $this->useCase->execute($dto);

        $this->assertSame(0.0, $item->stockQuantity);
        $this->assertSame(0.0, $movement->currentQuantity);
    }
}
