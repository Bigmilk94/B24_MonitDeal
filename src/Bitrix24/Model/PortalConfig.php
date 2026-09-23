<?php

declare(strict_types=1);

namespace App\Bitrix24\Model;

/**
 * Per-portal configuration set from the /ustawienia panel: which funnels
 * ("lejki" — Bitrix24 calls them deal categories) to show on the dashboard,
 * and any manual override of a stage's open/won/lost semantics on top of
 * what Bitrix24 itself reports (SEMANTICS: P/S/F) for that stage.
 */
final class PortalConfig
{
    /**
     * @param list<int> $funnelIds Empty = show every funnel (default until configured).
     * @param array<string, string> $stageSemanticOverrides STATUS_ID => 'open'|'won'|'lost'
     */
    public function __construct(
        public readonly array $funnelIds = [],
        public readonly array $stageSemanticOverrides = [],
    ) {
    }

    /**
     * @return array{funnelIds: list<int>, stageSemanticOverrides: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'funnelIds' => array_values($this->funnelIds),
            'stageSemanticOverrides' => $this->stageSemanticOverrides,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map('intval', $data['funnelIds'] ?? []),
            array_map('strval', $data['stageSemanticOverrides'] ?? []),
        );
    }

    public function includesFunnel(int $funnelId): bool
    {
        return $this->funnelIds === [] || in_array($funnelId, $this->funnelIds, true);
    }

    public function withFunnelIds(array $funnelIds): self
    {
        return new self(array_values($funnelIds), $this->stageSemanticOverrides);
    }

    public function withStageSemanticOverrides(array $overrides): self
    {
        return new self($this->funnelIds, $overrides);
    }
}
