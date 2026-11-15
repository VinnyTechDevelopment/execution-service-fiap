<?php

namespace Tests\Unit\Application\Item\UseCases;

use App\Application\Item\DTOs\CreateItemDTO;
use App\Application\Item\UseCases\CreateItemUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CreateItemUseCaseTest extends TestCase
{
    private MockInterface $repository;

    private CreateItemUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ItemRepositoryInterface::class);
        $this->useCase = new CreateItemUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDTO(string $code = 'ITEM-001'): CreateItemDTO
    {
        return new CreateItemDTO(
            name: 'Brake Pad',
            code: $code,
            type: 'part',
            measureUnit: 'un',
            minimumQuantity: 2.0,
            description: null,
            unitPrice: 49.90
        );
    }

    public function test_creates_item_and_saves_to_repository(): void
    {
        $this->repository
            ->shouldReceive('findByCode')
            ->once()
            ->with('ITEM-001')
            ->andReturnNull();

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::type(Item::class));

        $item = $this->useCase->execute($this->makeDTO());

        $this->assertInstanceOf(Item::class, $item);
        $this->assertSame('Brake Pad', $item->name);
        $this->assertSame('ITEM-001', $item->code->getValue());
        $this->assertSame(0.0, $item->stockQuantity);
    }

    public function test_throws_domain_exception_when_code_already_exists(): void
    {
        $existingItem = Mockery::mock(Item::class);

        $this->repository
            ->shouldReceive('findByCode')
            ->once()
            ->with('ITEM-001')
            ->andReturn($existingItem);

        $this->repository->shouldNotReceive('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('An item with this code already exists.');

        $this->useCase->execute($this->makeDTO());
    }

    public function test_auto_uppercases_code_before_duplicate_check(): void
    {
        $this->repository
            ->shouldReceive('findByCode')
            ->once()
            ->with('ITEM-001')
            ->andReturnNull();

        $this->repository
            ->shouldReceive('save')
            ->once();

        $dto = $this->makeDTO(code: 'item-001');
        $item = $this->useCase->execute($dto);

        $this->assertSame('ITEM-001', $item->code->getValue());
    }
}
