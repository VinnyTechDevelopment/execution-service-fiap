<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\DTOs\ShowItemDTO;
use App\Application\Item\UseCases\ShowItemUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ShowItemUseCaseTest extends TestCase
{
    private ItemRepositoryInterface&MockInterface $repository;

    private ShowItemUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ItemRepositoryInterface::class);
        $this->useCase = new ShowItemUseCase($this->repository);
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
            stockQuantity: 10.0,
            minimumQuantity: 2.0,
            description: 'Front brake pad',
            unitPrice: 49.90
        );
    }

    public function test_returns_item_when_found(): void
    {
        $item = $this->makeItem('uuid-1111');
        $dto = new ShowItemDTO('uuid-1111');

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('uuid-1111')
            ->andReturn($item);

        $result = $this->useCase->execute($dto);

        $this->assertInstanceOf(Item::class, $result);
        $this->assertSame('uuid-1111', $result->id);
        $this->assertSame('Brake Pad', $result->name);
        $this->assertSame('BP-001', $result->code->getValue());
    }

    public function test_throws_domain_exception_when_item_not_found(): void
    {
        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('uuid-9999')
            ->andReturnNull();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item not found.');

        $this->useCase->execute(new ShowItemDTO('uuid-9999'));
    }

    public function test_passes_id_to_repository(): void
    {
        $item = $this->makeItem('uuid-ABCD');

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('uuid-ABCD')
            ->andReturn($item);

        $result = $this->useCase->execute(new ShowItemDTO('uuid-ABCD'));
        $this->assertSame('uuid-ABCD', $result->id);
    }
}
