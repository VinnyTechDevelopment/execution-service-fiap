<?php

namespace Tests\Unit\Application\Service\UseCases;

use App\Application\Service\DTOs\ListServiceDTO;
use App\Application\Service\UseCases\ListServiceUseCase;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ListServiceUseCaseTest extends TestCase
{
    private MockInterface $repository;

    private ListServiceUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->useCase = new ListServiceUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calls_find_all_when_no_page_provided(): void
    {
        $services = ['svc-1', 'svc-2'];

        $this->repository->shouldReceive('findAll')->once()->andReturn($services);
        $this->repository->shouldNotReceive('paginate');

        $result = $this->useCase->execute(new ListServiceDTO(null, null));

        $this->assertSame($services, $result);
    }

    public function test_calls_paginate_when_page_provided(): void
    {
        $paginated = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => 15];

        $this->repository->shouldReceive('paginate')->once()->with(1, 15)->andReturn($paginated);
        $this->repository->shouldNotReceive('findAll');

        $result = $this->useCase->execute(new ListServiceDTO(1, 15));

        $this->assertSame($paginated, $result);
    }

    public function test_defaults_per_page_to_10_when_invalid(): void
    {
        $this->repository->shouldReceive('paginate')->once()->with(1, 10)->andReturn(['data' => []]);

        $result = $this->useCase->execute(new ListServiceDTO(1, 0));

        $this->assertSame(['data' => []], $result);
    }
}
