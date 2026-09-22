<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * HTML-escape a value for safe output in templates.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money')) {
    function money(float $value, string $currency): string
    {
        return number_format($value, 0, ',', ' ') . ' ' . $currency;
    }
}

if (!function_exists('qs')) {
    /**
     * Build a query string from the current GET params with some keys
     * overridden — used for sortable column headers and filter links that
     * must preserve every other active filter.
     *
     * @param array<string, mixed> $overrides
     */
    function qs(array $overrides): string
    {
        $params = array_merge($_GET, $overrides);
        $params = array_filter($params, static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);

        return '?' . http_build_query($params);
    }
}
