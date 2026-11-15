<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Service\DTOs\CreateServiceDTO;
use App\Application\Service\DTOs\ListServiceDTO;
use App\Application\Service\DTOs\ShowServiceDTO;
use App\Application\Service\DTOs\UpdateServiceDTO;
use App\Application\Service\UseCases\CreateServiceUseCase;
use App\Application\Service\UseCases\DeleteServiceUseCase;
use App\Application\Service\UseCases\ListServiceUseCase;
use App\Application\Service\UseCases\ShowServiceUseCase;
use App\Application\Service\UseCases\UpdateServiceUseCase;
use App\Presentation\Http\Requests\CreateServiceRequest;
use App\Presentation\Http\Requests\UpdateServiceRequest;
use Exception;
use Illuminate\Http\Request;

class ServiceController
{
    public function list(Request $request, ListServiceUseCase $useCase)
    {
        $page = $request->query('page');
        $perPage = $request->query('per_page');

        $dto = new ListServiceDTO(
            $page !== null ? (int) $page : null,
            $perPage !== null ? (int) $perPage : null
        );

        $services = $useCase->execute($dto);

        return response()->json($services);
    }

    public function store(CreateServiceRequest $request, CreateServiceUseCase $useCase)
    {
        try {
            $dto = new CreateServiceDTO(
                $request->name,
                $request->price
            );

            $service = $useCase->execute($dto);

            return response()->json([
                'id' => $service->id,
                'message' => 'Serviço cadastrado com sucesso',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(string $id, ShowServiceUseCase $useCase)
    {
        $dto = new ShowServiceDTO(
            id: $id
        );

        $service = $useCase->execute($dto);

        return response()->json($service);
    }

    public function update(UpdateServiceRequest $request, UpdateServiceUseCase $useCase)
    {
        $dto = new UpdateServiceDTO(
            id: $request->route('id'),
            name: $request->input('name'),
            price: $request->input('price')
        );

        $service = $useCase->execute($dto);

        return response()->json([
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'price' => $service->price,
            ],
            'message' => 'Serviço atualizado com sucesso',
        ], 200);
    }

    public function destroy(string $id, DeleteServiceUseCase $useCase)
    {
        $useCase->execute($id);

        return response()->json(null, 204);
    }
}
