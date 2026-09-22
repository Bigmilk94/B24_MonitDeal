<?php

declare(strict_types=1);

namespace App\Twig;

use App\Support\TimeFormatter;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Small, app-specific Twig helpers: currency formatting, Polish date/time
 * formatting (delegates to Support\TimeFormatter, shared with the domain
 * layer), and building a query string that preserves the current request's
 * filters while overriding a few keys (used by sortable column headers and
 * quick-filter links).
 */
final class AppExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
            new TwigFilter('date_pl', $this->datePl(...)),
            new TwigFilter('datetime_pl', $this->dateTimePl(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('merge_query', $this->mergeQuery(...)),
        ];
    }

    public function money(float $value, string $currency): string
    {
        return number_format($value, 0, ',', ' ') . ' ' . $currency;
    }

    public function datePl(DateTimeInterface $dt): string
    {
        return TimeFormatter::date($this->toImmutable($dt));
    }

    public function dateTimePl(DateTimeInterface $dt): string
    {
        return TimeFormatter::dateTime($this->toImmutable($dt));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function mergeQuery(array $overrides): string
    {
        $current = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
        $params = array_merge($current, $overrides);
        $params = array_filter($params, static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);

        return '?' . http_build_query($params);
    }

    private function toImmutable(DateTimeInterface $dt): DateTimeImmutable
    {
        return $dt instanceof DateTimeImmutable ? $dt : DateTimeImmutable::createFromInterface($dt);
    }
}
