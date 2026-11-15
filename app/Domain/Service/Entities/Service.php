<?php

namespace App\Domain\Service\Entities;

use Illuminate\Support\Str;

class Service
{
    public function __construct(
        public string $id,
        public string $name,
        public float $price,
    ) {}

    public static function create(
        string $name,
        float $price
    ): self {
        return new self(
            Str::uuid()->toString(),
            $name,
            $price
        );
    }

    public function updateData(
        ?string $name,
        ?float $price
    ): void {
        if ($name !== null) {
            $this->name = $name;
        }

        if ($price !== null) {
            $this->price = $price;
        }
    }
}
