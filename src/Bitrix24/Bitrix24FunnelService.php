<?php

declare(strict_types=1);

namespace App\Bitrix24;

use App\Bitrix24\Model\Portal;
use App\Domain\Enum\DealSemantic;
use App\Domain\Model\DealStage;

/**
 * Reads a portal's deal funnels ("lejki" — Bitrix24 calls them deal
 * categories) and each funnel's stages, including Bitrix24's own
 * won/lost/in-progress semantics per stage. Used by both the settings
 * panel (to let the admin pick which funnels to track) and
 * Bitrix24CrmService (to classify deals as open/won/lost).
 *
 * Shared here instead of duplicated so both places agree on what a
 * "funnel" and a "stage semantic" are.
 */
final class Bitrix24FunnelService
{
    private const DEFAULT_FUNNEL_ID = 0;

    public function __construct(private readonly Bitrix24Client $client)
    {
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listFunnels(Portal $portal): array
    {
        $funnels = [['id' => self::DEFAULT_FUNNEL_ID, 'name' => 'Domyślny lejek']];

        $rows = $this->client->callList($portal, 'crm.category.list', [
            'entityTypeId' => 2, // 2 = deals
        ]);

        foreach ($rows as $row) {
            $funnels[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];
        }

        return $funnels;
    }

    /**
     * @return list<DealStage> Stages for one funnel, each carrying Bitrix24's
     *     own semantic for that stage (SEMANTICS: null = in progress,
     *     "S" = won/success, "F" = lost/failure).
     */
    public function listStages(Portal $portal, int $funnelId): array
    {
        $entityId = $funnelId === self::DEFAULT_FUNNEL_ID ? 'DEAL_STAGE' : "DEAL_STAGE_{$funnelId}";

        $rows = $this->client->callList($portal, 'crm.status.list', [
            'filter' => ['ENTITY_ID' => $entityId],
        ]);

        $overrides = $portal->config->stageSemanticOverrides;

        return array_map(static function (array $row) use ($overrides): DealStage {
            $statusId = (string) $row['STATUS_ID'];
            $semantic = match ($row['SEMANTICS'] ?? null) {
                'S' => DealSemantic::WON,
                'F' => DealSemantic::LOST,
                default => DealSemantic::OPEN,
            };
            if (isset($overrides[$statusId])) {
                $semantic = DealSemantic::from($overrides[$statusId]);
            }

            return new DealStage($statusId, (string) $row['NAME'], $semantic);
        }, $rows);
    }

    /**
     * All stages of all of the portal's funnels, keyed by STATUS_ID, for
     * quickly resolving a deal's STAGE_ID to a DealStage while mapping.
     *
     * @return array<string, DealStage>
     */
    public function allStagesByStatusId(Portal $portal): array
    {
        $byId = [];
        foreach ($this->listFunnels($portal) as $funnel) {
            foreach ($this->listStages($portal, $funnel['id']) as $stage) {
                $byId[$stage->value] = $stage;
            }
        }

        return $byId;
    }
}
