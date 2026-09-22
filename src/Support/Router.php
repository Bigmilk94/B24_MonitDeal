<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal, dependency-free router: pattern like "/deals/{id}" mapped to a
 * handler that receives (Request $request, array $params).
 */
final class Router
{
    /** @var list<array{method:string, pattern:string, handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => 'GET', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(Request $request, string $method): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, rtrim($request->path, '/') ?: '/', $matches) === 1) {
                $params = array_filter(
                    $matches,
                    static fn (int|string $key): bool => is_string($key),
                    ARRAY_FILTER_USE_KEY,
                );
                ($route['handler'])($request, $params);

                return;
            }
        }

        http_response_code(404);
        echo '404 — nie znaleziono strony.';
    }

    private function compile(string $pattern): string
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $escaped = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $escaped . '$#u';
    }
}
