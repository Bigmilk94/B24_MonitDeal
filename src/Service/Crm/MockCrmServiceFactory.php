<?php

declare(strict_types=1);

namespace App\Service\Crm;

use Psr\Clock\ClockInterface;

/**
 * Wires MockCrmService to the container's clock so it seeds its demo data
 * from the same "now" the rest of the request uses, without forcing
 * MockCrmService itself to know anything about the DI container.
 */
final class MockCrmServiceFactory
{
    public static function create(ClockInterface $clock): MockCrmService
    {
        return new MockCrmService($clock->now());
    }
}
