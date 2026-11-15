<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Catalog\UseCases\LookupCatalogUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogLookupController
{
    public function __invoke(Request $request, LookupCatalogUseCase $useCase): JsonResponse
    {
        $validated = $request->validate([
            'services' => 'required|array|min:1',
            'services.*.service_id' => 'required|string',
            'services.*.quantity' => 'required|numeric|min:0.01',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
        ]);

        return response()->json($useCase->execute($validated['services'], $validated['items'] ?? []));
    }
}
