<?php

declare(strict_types=1);

use Bilge\Tracing\Logging\LogIngestClient;

/**
 * Entry point: {@code bilge_logger()->info('…');} then {@code bilge_logger()->flush();}
 */
function bilge_logger(): LogIngestClient
{
    return LogIngestClient::instance();
}
