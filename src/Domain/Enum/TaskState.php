<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Stored state of a Task activity. "Overdue" is not stored here — it is derived
 * at read time by comparing a PENDING task's due date against the current moment,
 * so it is always correct regardless of when the page is rendered.
 */
enum TaskState: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
}
