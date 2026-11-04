<?php

namespace App\Application\Catalog\UseCases;

use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

/**
 * Consulta síncrona (REST) usada pelo os-service ao abrir uma OS: valida que
 * serviços e peças existem e devolve nome e preço vigentes (snapshot que vai
 * para o orçamento).
 */
class LookupCatalogUseCase
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private ItemRepositoryInterface $items,
    ) {}

    /**
     * @param  array<int, array{service_id: string, quantity: float|int}>  $services
     * @param  array<int, array{item_id: string, quantity: float|int}>  $items
     */
    public function execute(array $services, array $items): array
    {
        $resolvedServices = array_map(function (array $line): array {
            $service = $this->services->findById((string) $line['service_id'])
                ?? throw new \DomainException("Servico '{$line['service_id']}' nao encontrado.");

            return [
                'service_id' => $service->id,
                'name' => $service->name,
                'quantity' => (float) $line['quantity'],
                'unit_price' => (float) $service->price,
            ];
        }, $services);

        $resolvedItems = array_map(function (array $line): array {
            $item = $this->items->findById((string) $line['item_id'])
                ?? throw new \DomainException("Peca '{$line['item_id']}' nao encontrada.");

            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'quantity' => (float) $line['quantity'],
                'unit_price' => (float) ($item->unitPrice ?? 0.0),
                'available_stock' => $item->stockQuantity,
            ];
        }, $items);

        return ['services' => array_values($resolvedServices), 'items' => array_values($resolvedItems)];
    }
}
