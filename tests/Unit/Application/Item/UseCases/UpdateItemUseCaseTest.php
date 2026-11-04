<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\DTOs\UpdateItemDTO;
use App\Application\Item\UseCases\UpdateItemUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class UpdateItemUseCaseTest extends TestCase
{
    private ItemRepositoryInterface&MockInterface $repository;

    private UpdateItemUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ItemRepositoryInterface::class);
        $this->useCase = new UpdateItemUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeItem(string $id = 'uuid-1111'): Item
    {
        return new Item(
            id: $id,
            name: 'Original Name',
            code: new ItemCode('ORIG-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit(MeasureUnit::UNIT),
            stockQuantity: 0.0,
            minimumQuantity: 2.0,
            description: null,
            unitPrice: null
        );
    }

    public function test_updates_item_name_successfully(): void
    {
        $item = $this->makeItem();
        $dto = new UpdateItemDTO(
            id: 'uuid-1111',
            name: 'Updated Name',
            code: null,
            type: null,
            measureUnit: null,
            minimumQuantity: null,
            description: null,
            unitPrice: null
        );

        $this->repository->shouldReceive('findById')->once()->with('uuid-1111')->andReturn($item);
        $this->repository->shouldReceive('update')->once()->with($item);

        $result = $this->useCase->execute($dto);

        $this->assertSame('Updated Name', $result->name);
    }

    public function test_throws_when_item_not_found(): void
    {
        $this->repository->shouldReceive('findById')->once()->andReturnNull();
        $this->repository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item not found.');

        $this->useCase->execute(new UpdateItemDTO('uuid-9999', null, null, null, null, null, null, null));
    }

    public function test_throws_when_new_code_belongs_to_another_item(): void
    {
        $item = $this->makeItem('uuid-1111');
        $otherItem = $this->makeItem('uuid-2222');

        $dto = new UpdateItemDTO('uuid-1111', null, 'NEW-001', null, null, null, null, null);

        $this->repository->shouldReceive('findById')->once()->with('uuid-1111')->andReturn($item);
        $this->repository->shouldReceive('findByCode')->once()->with('NEW-001')->andReturn($otherItem);
        $this->repository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Another item already uses this code.');

        $this->useCase->execute($dto);
    }

    public function test_allows_updating_to_same_code_of_same_item(): void
    {
        $item = $this->makeItem('uuid-1111');
        $dto = new UpdateItemDTO('uuid-1111', null, 'ORIG-001', null, null, null, null, null);

        $this->repository->shouldReceive('findById')->once()->with('uuid-1111')->andReturn($item);
        $this->repository->shouldReceive('findByCode')->once()->with('ORIG-001')->andReturn($item);
        $this->repository->shouldReceive('update')->once()->with($item);

        $result = $this->useCase->execute($dto);

        $this->assertSame('ORIG-001', $result->code->getValue());
    }

    public function test_skips_code_duplicate_check_when_code_not_provided(): void
    {
        $item = $this->makeItem();
        $dto = new UpdateItemDTO('uuid-1111', 'New Name', null, null, null, null, null, null);

        $this->repository->shouldReceive('findById')->once()->andReturn($item);
        $this->repository->shouldNotReceive('findByCode');
        $this->repository->shouldReceive('update')->once();

        $result = $this->useCase->execute($dto);
        $this->assertSame('New Name', $result->name);
    }
}
