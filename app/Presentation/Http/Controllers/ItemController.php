<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Item\DTOs\CreateItemDTO;
use App\Application\Item\DTOs\ListItemDTO;
use App\Application\Item\DTOs\ShowItemDTO;
use App\Application\Item\DTOs\UpdateItemDTO;
use App\Application\Item\UseCases\CreateItemUseCase;
use App\Application\Item\UseCases\DeleteItemUseCase;
use App\Application\Item\UseCases\ListItemUseCase;
use App\Application\Item\UseCases\ShowItemUseCase;
use App\Application\Item\UseCases\UpdateItemUseCase;
use App\Presentation\Http\Requests\CreateItemRequest;
use App\Presentation\Http\Requests\ListItemRequest;
use App\Presentation\Http\Requests\UpdateItemRequest;
use Illuminate\Http\JsonResponse;

class ItemController
{
    public function store(CreateItemRequest $request, CreateItemUseCase $useCase): JsonResponse
    {
        $dto = new CreateItemDTO(
            name: $request->name,
            code: $request->code,
            type: $request->type,
            measureUnit: $request->measure_unit,
            minimumQuantity: (float) $request->minimum_quantity,
            description: $request->description,
            unitPrice: $request->unit_price !== null ? (float) $request->unit_price : null
        );

        $item = $useCase->execute($dto);

        return response()->json([
            'id' => $item->id,
            'message' => 'Item registered successfully.',
        ], 201);
    }

    public function list(ListItemRequest $request, ListItemUseCase $useCase): JsonResponse
    {
        $dto = new ListItemDTO(
            page: $request->input('page', 1),
            perPage: $request->input('perPage', 10),
            type: $request->input('type')
        );

        return response()->json($useCase->execute($dto));
    }

    public function show(string $id, ShowItemUseCase $useCase): JsonResponse
    {
        $item = $useCase->execute(new ShowItemDTO(id: $id));

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code->getValue(),
            'type' => $item->type->getValue(),
            'description' => $item->description,
            'measure_unit' => $item->measureUnit->getValue(),
            'stock_quantity' => $item->stockQuantity,
            'minimum_quantity' => $item->minimumQuantity,
            'unit_price' => $item->unitPrice,
            'is_low_stock' => $item->isLowStock(),
        ]);
    }

    public function update(UpdateItemRequest $request, UpdateItemUseCase $useCase): JsonResponse
    {
        $dto = new UpdateItemDTO(
            id: $request->route('id'),
            name: $request->input('name'),
            code: $request->input('code'),
            type: $request->input('type'),
            measureUnit: $request->input('measure_unit'),
            minimumQuantity: $request->input('minimum_quantity') !== null
                ? (float) $request->input('minimum_quantity')
                : null,
            description: $request->input('description'),
            unitPrice: $request->input('unit_price') !== null
                ? (float) $request->input('unit_price')
                : null
        );

        $item = $useCase->execute($dto);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code->getValue(),
                'type' => $item->type->getValue(),
                'description' => $item->description,
                'measure_unit' => $item->measureUnit->getValue(),
                'stock_quantity' => $item->stockQuantity,
                'minimum_quantity' => $item->minimumQuantity,
                'unit_price' => $item->unitPrice,
                'is_low_stock' => $item->isLowStock(),
            ],
            'message' => 'Item updated successfully.',
        ]);
    }

    public function destroy(string $id, DeleteItemUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return response()->json(['message' => 'Item removed successfully.']);
    }
}
