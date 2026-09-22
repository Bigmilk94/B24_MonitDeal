<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class Company
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $industry,
    ) {
    }
}
