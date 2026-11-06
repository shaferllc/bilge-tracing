<?php

declare(strict_types=1);

namespace Bilge\Tracing\Job;

/**
 * Queue job run status values for Bilge job ingest.
 */
final class RunStatus
{
    public static function inProgress(): string
    {
        return 'in_progress';
    }

    public static function ok(): string
    {
        return 'ok';
    }

    public static function error(): string
    {
        return 'error';
    }
}
