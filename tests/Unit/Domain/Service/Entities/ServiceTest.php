<?php

namespace Tests\Unit\Domain\Service\Entities;

use App\Domain\Service\Entities\Service;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    public function test_create_generates_uuid_and_sets_fields(): void
    {
        $service = Service::create('Alinhamento', 90.0);

        $this->assertNotEmpty($service->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $service->id
        );
        $this->assertSame('Alinhamento', $service->name);
        $this->assertSame(90.0, $service->price);
    }

    public function test_update_data_changes_only_non_null_fields(): void
    {
        $service = new Service('svc-1', 'Alinhamento', 90.0);

        $service->updateData('Balanceamento', null);

        $this->assertSame('Balanceamento', $service->name);
        $this->assertSame(90.0, $service->price);
    }

    public function test_update_data_with_all_nulls_changes_nothing(): void
    {
        $service = new Service('svc-1', 'Alinhamento', 90.0);

        $service->updateData(null, null);

        $this->assertSame('Alinhamento', $service->name);
        $this->assertSame(90.0, $service->price);
    }
}
