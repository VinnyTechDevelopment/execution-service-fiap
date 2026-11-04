<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\UseCases\DeleteItemUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DeleteItemUseCaseTest extends TestCase
{
    private ItemRepositoryInterface&MockInterface $repository;

    private DeleteItemUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ItemRepositoryInterface::class);
        $this->useCase = new DeleteItemUseCase($this->repository);
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

    public function test_deletes_item_with_zero_stock(): void
    {
        $item = $this->makeItem(stock: 0.0);

        $this->repository->shouldReceive('findById')->once()->with('uuid-1111')->andReturn($item);
        $this->repository->shouldReceive('delete')->once()->with('uuid-1111')->andReturnNull();

        $result = $this->useCase->execute('uuid-1111');
        $this->assertTrue(true); // Verify delete was called via mock expectations
    }

    public function test_throws_when_item_not_found(): void
    {
        $this->repository->shouldReceive('findById')->once()->andReturnNull();
        $this->repository->shouldNotReceive('delete');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item not found.');

        $this->useCase->execute('uuid-9999');
    }

    public function test_throws_when_item_has_stock(): void
    {
        $item = $this->makeItem(stock: 10.0);

        $this->repository->shouldReceive('findById')->once()->andReturn($item);
        $this->repository->shouldNotReceive('delete');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot delete an item with available stock.');

        $this->useCase->execute('uuid-1111');
    }
}
