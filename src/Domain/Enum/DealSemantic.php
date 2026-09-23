<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * What a deal stage *means*, regardless of which CRM it comes from or what
 * its human-readable name is. Bitrix24 (and most CRMs) tag every stage with
 * exactly this: in-progress (P), won (S — "success"), or lost (F —
 * "failure"). This is the one truly universal, fixed part of "stage" — the
 * stage's id and label are not (see Domain\Model\DealStage).
 */
enum DealSemantic: string
{
    case OPEN = 'open';
    case WON = 'won';
    case LOST = 'lost';
}
