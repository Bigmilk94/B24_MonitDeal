<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Shared completed/planned state for activities that are either already
 * carried out or still scheduled: calls, meetings, and generic CRM activities.
 */
enum SchedulableState: string
{
    case DONE = 'done';
    case PLANNED = 'planned';
}
