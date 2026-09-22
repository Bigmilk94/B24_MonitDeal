<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    /**
     * @param array<string, mixed> $query
     */
    private function __construct(
        public readonly string $path,
        public readonly array $query,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return new self($path, $_GET);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }
}
