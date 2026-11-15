<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\DTOs\ListStockMovementsDTO;
use App\Application\Item\UseCases\ListStockMovementsUseCase;
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

class ListStockMovementsUseCaseTest extends TestCase
{
    private ItemRepositoryInterface&MockInterface $itemRepository;

    private StockMovementRepositoryInterface&MockInterface $movementRepository;

    private ListStockMovementsUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $this->movementRepository = Mockery::mock(StockMovementRepositoryInterface::class);
        $this->useCase = new ListStockMovementsUseCase($this->itemRepository, $this->movementRepository);
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
            name: 'Brake Pad',
            code: new ItemCode('BP-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit(MeasureUnit::UNIT),
            stockQuantity: 20.0,
            minimumQuantity: 2.0,
            description: null,
            unitPrice: null
        );
    }

    private function makeMovement(string $itemId = 'uuid-1111'): StockMovement
    {
        return StockMovement::record(
            itemId: $itemId,
            movementType: new MovementType(MovementType::ENTRY),
            quantity: 10.0,
            previousQuantity: 0.0,
            currentQuantity: 10.0,
            reason: 'Purchase NF 001',
            notes: null
        );
    }

    public function test_returns_movements_for_existing_item(): void
    {
        $item = $this->makeItem('uuid-1111');
        $movements = ['data' => [$this->makeMovement()], 'total' => 1, 'page' => 1, 'perPage' => 10];
        $dto = new ListStockMovementsDTO(itemId: 'uuid-1111', page: 1, perPage: 10);

        $this->itemRepository
            ->shouldReceive('findById')
            ->once()
            ->with('uuid-1111')
            ->andReturn($item);

        $this->movementRepository
            ->shouldReceive('findByItemId')
            ->once()
            ->with('uuid-1111', 1, 10)
            ->andReturn($movements);

        $result = $this->useCase->execute($dto);

        $this->assertSame($movements, $result);
    }

    public function test_throws_domain_exception_when_item_not_found(): void
    {
        $dto = new ListStockMovementsDTO(itemId: 'uuid-9999', page: 1, perPage: 10);

        $this->itemRepository
            ->shouldReceive('findById')
            ->once()
            ->with('uuid-9999')
            ->andReturnNull();

        $this->movementRepository->shouldNotReceive('findByItemId');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item not found.');

        $this->useCase->execute($dto);
    }

    public function test_returns_empty_list_for_item_with_no_movements(): void
    {
        $item = $this->makeItem('uuid-1111');
        $dto = new ListStockMovementsDTO(itemId: 'uuid-1111', page: 1, perPage: 10);

        $this->itemRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($item);

        $this->movementRepository
            ->shouldReceive('findByItemId')
            ->once()
            ->andReturn(['data' => [], 'total' => 0, 'page' => 1, 'perPage' => 10]);

        $result = $this->useCase->execute($dto);

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['data']);
    }

    public function test_passes_pagination_params_to_repository(): void
    {
        $item = $this->makeItem('uuid-1111');
        $dto = new ListStockMovementsDTO(itemId: 'uuid-1111', page: 3, perPage: 5);

        $this->itemRepository
            ->shouldReceive('findById')
            ->andReturn($item);

        $this->movementRepository
            ->shouldReceive('findByItemId')
            ->once()
            ->with('uuid-1111', 3, 5)
            ->andReturn([]);

        $result = $this->useCase->execute($dto);
        $this->assertSame([], $result);
    }
}
