<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * A CRM user — deal owner or the person who performed an activity.
 */
final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $initials,
    ) {
    }
}
