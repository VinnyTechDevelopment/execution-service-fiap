<?php

namespace Tests\Unit\Application\Service\UseCases;

use App\Application\Service\DTOs\CreateServiceDTO;
use App\Application\Service\UseCases\CreateServiceUseCase;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CreateServiceUseCaseTest extends TestCase
{
    private MockInterface $repository;

    private CreateServiceUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->useCase = new CreateServiceUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDTO(string $name = 'Troca de oleo', string $price = '120.50'): CreateServiceDTO
    {
        return new CreateServiceDTO($name, $price);
    }

    public function test_creates_service_and_saves_it(): void
    {
        $this->repository->shouldReceive('findByName')->once()->with('Troca de oleo')->andReturnNull();
        $this->repository->shouldReceive('save')->once()->with(Mockery::type(Service::class));

        $service = $this->useCase->execute($this->makeDTO());

        $this->assertInstanceOf(Service::class, $service);
        $this->assertSame('Troca de oleo', $service->name);
        $this->assertSame(120.50, $service->price);
    }

    public function test_throws_when_service_name_already_exists(): void
    {
        $this->repository->shouldReceive('findByName')->once()->with('Troca de oleo')->andReturn(new Service('svc-1', 'Troca de oleo', 100.0));
        $this->repository->shouldNotReceive('save');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Serviço já cadastrado');

        $this->useCase->execute($this->makeDTO());
    }
}
