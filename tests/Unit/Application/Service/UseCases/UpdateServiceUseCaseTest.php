<?php

namespace Tests\Unit\Application\Service\UseCases;

use App\Application\Service\DTOs\UpdateServiceDTO;
use App\Application\Service\UseCases\UpdateServiceUseCase;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class UpdateServiceUseCaseTest extends TestCase
{
    private MockInterface $repository;

    private UpdateServiceUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->useCase = new UpdateServiceUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(string $id = 'svc-1', string $name = 'Alinhamento', float $price = 90.0): Service
    {
        return new Service($id, $name, $price);
    }

    public function test_updates_service_successfully(): void
    {
        $service = $this->makeService();

        $this->repository->shouldReceive('findById')->once()->with('svc-1')->andReturn($service);
        $this->repository->shouldReceive('findByName')->once()->with('Balanceamento')->andReturnNull();
        $this->repository->shouldReceive('update')->once()->with($service);

        $result = $this->useCase->execute(new UpdateServiceDTO('svc-1', 'Balanceamento', '110.5'));

        $this->assertSame('Balanceamento', $result->name);
        $this->assertSame(110.5, $result->price);
    }

    public function test_throws_when_service_not_found(): void
    {
        $this->repository->shouldReceive('findById')->once()->with('svc-404')->andReturnNull();
        $this->repository->shouldNotReceive('update');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Serviço não encontrado');

        $this->useCase->execute(new UpdateServiceDTO('svc-404', 'Teste', '10'));
    }

    public function test_throws_when_no_data_is_provided(): void
    {
        $service = $this->makeService();

        $this->repository->shouldReceive('findById')->once()->with('svc-1')->andReturn($service);
        $this->repository->shouldNotReceive('findByName');
        $this->repository->shouldNotReceive('update');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nenhum dado para atualizar');

        $this->useCase->execute(new UpdateServiceDTO('svc-1', null, null));
    }

    public function test_throws_when_name_belongs_to_another_service(): void
    {
        $service = $this->makeService('svc-1');
        $other = $this->makeService('svc-2', 'Balanceamento', 95.0);

        $this->repository->shouldReceive('findById')->once()->with('svc-1')->andReturn($service);
        $this->repository->shouldReceive('findByName')->once()->with('Balanceamento')->andReturn($other);
        $this->repository->shouldNotReceive('update');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Já existe um serviço com esse nome');

        $this->useCase->execute(new UpdateServiceDTO('svc-1', 'Balanceamento', null));
    }
}
