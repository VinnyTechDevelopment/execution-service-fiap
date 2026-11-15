<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\DTOs\ListItemDTO;
use App\Application\Item\UseCases\ListItemUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ListItemUseCaseTest extends TestCase
{
    private ItemRepositoryInterface&MockInterface $repository;

    private ListItemUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ItemRepositoryInterface::class);
        $this->useCase = new ListItemUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeItem(string $code = 'ITEM-001', string $type = ItemType::PART): Item
    {
        return new Item(
            id: 'uuid-'.$code,
            name: 'Item '.$code,
            code: new ItemCode($code),
            type: new ItemType($type),
            measureUnit: new MeasureUnit(MeasureUnit::UNIT),
            stockQuantity: 0.0,
            minimumQuantity: 1.0,
            description: null,
            unitPrice: null
        );
    }

    public function test_calls_find_all_when_no_page_provided(): void
    {
        $dto = new ListItemDTO(page: null, perPage: null, type: null);
        $items = [$this->makeItem('ITEM-001'), $this->makeItem('ITEM-002')];

        $this->repository
            ->shouldReceive('findAll')
            ->once()
            ->with(null)
            ->andReturn($items);

        $this->repository->shouldNotReceive('paginate');

        $result = $this->useCase->execute($dto);

        $this->assertSame($items, $result);
    }

    public function test_calls_paginate_when_page_provided(): void
    {
        $dto = new ListItemDTO(page: 1, perPage: 15, type: null);
        $paginated = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => 15];

        $this->repository
            ->shouldReceive('paginate')
            ->once()
            ->with(1, 15, null)
            ->andReturn($paginated);

        $this->repository->shouldNotReceive('findAll');

        $result = $this->useCase->execute($dto);

        $this->assertSame($paginated, $result);
    }

    public function test_defaults_per_page_to_10_when_invalid(): void
    {
        $dto = new ListItemDTO(page: 1, perPage: 0, type: null);

        $this->repository
            ->shouldReceive('paginate')
            ->once()
            ->with(1, 10, null)
            ->andReturn([]);

        $result = $this->useCase->execute($dto);
        $this->assertSame([], $result);
    }

    public function test_passes_type_filter_to_find_all(): void
    {
        $dto = new ListItemDTO(page: null, perPage: null, type: 'part');
        $items = [$this->makeItem('ITEM-001', ItemType::PART)];

        $this->repository
            ->shouldReceive('findAll')
            ->once()
            ->with('part')
            ->andReturn($items);

        $result = $this->useCase->execute($dto);

        $this->assertSame($items, $result);
    }

    public function test_passes_type_filter_to_paginate(): void
    {
        $dto = new ListItemDTO(page: 2, perPage: 5, type: 'supply');

        $this->repository
            ->shouldReceive('paginate')
            ->once()
            ->with(2, 5, 'supply')
            ->andReturn([]);

        $result = $this->useCase->execute($dto);
        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_when_no_items_exist(): void
    {
        $dto = new ListItemDTO(page: null, perPage: null, type: null);

        $this->repository
            ->shouldReceive('findAll')
            ->once()
            ->andReturn([]);

        $result = $this->useCase->execute($dto);

        $this->assertSame([], $result);
    }
}
