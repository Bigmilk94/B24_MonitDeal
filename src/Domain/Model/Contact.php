<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class Contact
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $companyId,
        public readonly string $email,
        public readonly string $phone,
    ) {
    }
}
