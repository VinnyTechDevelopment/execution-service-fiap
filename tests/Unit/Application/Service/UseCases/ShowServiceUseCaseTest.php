<?php

namespace Tests\Unit\Application\Service\UseCases;

use App\Application\Service\DTOs\ShowServiceDTO;
use App\Application\Service\UseCases\ShowServiceUseCase;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ShowServiceUseCaseTest extends TestCase
{
    private MockInterface $repository;

    private ShowServiceUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->useCase = new ShowServiceUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_service_when_found(): void
    {
        $service = new Service('svc-1', 'Alinhamento', 90.0);

        $this->repository->shouldReceive('findById')->once()->with('svc-1')->andReturn($service);

        $result = $this->useCase->execute(new ShowServiceDTO('svc-1'));

        $this->assertSame($service, $result);
    }

    public function test_throws_when_service_not_found(): void
    {
        $this->repository->shouldReceive('findById')->once()->with('svc-404')->andReturnNull();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Serviço não encontrado');

        $this->useCase->execute(new ShowServiceDTO('svc-404'));
    }
}
