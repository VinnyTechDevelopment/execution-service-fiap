<?php

namespace Tests\Feature\Catalog;

use Tests\Support\UsesInMemoryRepositories;
use Tests\TestCase;

/**
 * Endpoints de catálogo/estoque migrados do monólito (antes /api/item e
 * /api/service, agora /api/catalog/items e /api/catalog/services).
 */
class CatalogApiTest extends TestCase
{
    use UsesInMemoryRepositories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindInMemoryRepositories();
    }

    public function test_item_crud_and_stock_flow(): void
    {
        $id = $this->postJson('/api/catalog/items', [
            'name' => 'Filtro de oleo',
            'code' => 'FLT-001',
            'type' => 'part',
            'measure_unit' => 'un',
            'minimum_quantity' => 2,
            'unit_price' => 35.9,
        ], $this->authHeaders())->assertCreated()->json('id');

        $this->postJson('/api/catalog/items', [
            'name' => 'Duplicado', 'code' => 'FLT-001', 'type' => 'part', 'measure_unit' => 'un', 'minimum_quantity' => 1,
        ], $this->authHeaders())->assertUnprocessable();

        $this->postJson("/api/catalog/items/{$id}/stock/entry", ['quantity' => 10, 'reason' => 'compra'], $this->authHeaders())
            ->assertCreated()->assertJsonPath('current_quantity', 10);
        $this->postJson("/api/catalog/items/{$id}/stock/withdrawal", ['quantity' => 3, 'reason' => 'uso'], $this->authHeaders())
            ->assertCreated()->assertJsonPath('current_quantity', 7);
        $this->postJson("/api/catalog/items/{$id}/stock/withdrawal", ['quantity' => 30, 'reason' => 'uso'], $this->authHeaders())
            ->assertUnprocessable();

        $this->getJson("/api/catalog/items/{$id}/stock/movements", $this->authHeaders())
            ->assertOk()->assertJsonPath('total', 2);

        $this->getJson("/api/catalog/items/{$id}", $this->authHeaders())
            ->assertOk()->assertJsonPath('stock_quantity', 7)->assertJsonPath('is_low_stock', false);

        $this->putJson("/api/catalog/items/{$id}", ['name' => 'Filtro premium'], $this->authHeaders())->assertOk();
        $this->getJson('/api/catalog/items?page=1&perPage=5', $this->authHeaders())->assertOk()->assertJsonPath('total', 1);

        $this->deleteJson("/api/catalog/items/{$id}", [], $this->authHeaders())->assertUnprocessable();
    }

    public function test_service_crud(): void
    {
        $this->postJson('/api/catalog/services', ['name' => 'Balanceamento', 'price' => 90], $this->authHeaders())
            ->assertSuccessful();

        $id = array_key_first($this->serviceRepo->services);

        $this->getJson("/api/catalog/services/{$id}", $this->authHeaders())->assertOk();
        $this->putJson("/api/catalog/services/{$id}", ['price' => 95], $this->authHeaders())->assertOk();
        $this->getJson('/api/catalog/services', $this->authHeaders())->assertOk()->assertJsonCount(1);
        $this->deleteJson("/api/catalog/services/{$id}", [], $this->authHeaders())->assertSuccessful();
        $this->assertSame([], $this->serviceRepo->services);
    }
}
