<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Item\DTOs\ListStockMovementsDTO;
use App\Application\Item\DTOs\StockEntryDTO;
use App\Application\Item\DTOs\StockWithdrawalDTO;
use App\Application\Item\UseCases\ListStockMovementsUseCase;
use App\Application\Item\UseCases\StockEntryUseCase;
use App\Application\Item\UseCases\StockWithdrawalUseCase;
use App\Presentation\Http\Requests\ListStockMovementsRequest;
use App\Presentation\Http\Requests\StockEntryRequest;
use App\Presentation\Http\Requests\StockWithdrawalRequest;
use Illuminate\Http\JsonResponse;

class StockController
{
    public function entry(string $id, StockEntryRequest $request, StockEntryUseCase $useCase): JsonResponse
    {
        $dto = new StockEntryDTO(
            itemId: $id,
            quantity: (float) $request->quantity,
            reason: $request->reason,
            notes: $request->notes
        );

        $movement = $useCase->execute($dto);

        return response()->json([
            'movement_id' => $movement->id,
            'type' => $movement->movementType->getValue(),
            'quantity' => $movement->quantity,
            'previous_quantity' => $movement->previousQuantity,
            'current_quantity' => $movement->currentQuantity,
            'message' => 'Stock entry registered successfully.',
        ], 201);
    }

    public function withdrawal(string $id, StockWithdrawalRequest $request, StockWithdrawalUseCase $useCase): JsonResponse
    {
        $dto = new StockWithdrawalDTO(
            itemId: $id,
            quantity: (float) $request->quantity,
            reason: $request->reason,
            notes: $request->notes
        );

        $movement = $useCase->execute($dto);

        return response()->json([
            'movement_id' => $movement->id,
            'type' => $movement->movementType->getValue(),
            'quantity' => $movement->quantity,
            'previous_quantity' => $movement->previousQuantity,
            'current_quantity' => $movement->currentQuantity,
            'message' => 'Stock withdrawal registered successfully.',
        ], 201);
    }

    public function movements(string $id, ListStockMovementsRequest $request, ListStockMovementsUseCase $useCase): JsonResponse
    {
        $dto = new ListStockMovementsDTO(
            itemId: $id,
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('perPage', 20)
        );

        return response()->json($useCase->execute($dto));
    }
}
