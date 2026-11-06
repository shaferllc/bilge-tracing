<?php

declare(strict_types=1);

namespace Bilge\Tracing\Testing;

use Bilge\Tracing\Tracer;

/**
 * Test helpers for asserting trace export shape without coupling to HTTP.
 */
final class TracerInspection
{
    /**
     * @return array<string, mixed>
     */
    public static function traceIngestBody(): array
    {
        return Tracer::instance()->buildTraceIngestBody();
    }
}
