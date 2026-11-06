<?php

declare(strict_types=1);

use Bilge\Tracing\Metrics\MetricsIngestClient;

/**
 * Bilge metrics entry point:
 * {@code bilge_metrics()->count('orders', 1);}
 * {@code bilge_metrics()->start('checkout')->stop(['ok' => true]);}
 * {@code bilge_metrics()->metric('latency')->attributes([...])->distribution(42.0);}
 */
function bilge_metrics(): MetricsIngestClient
{
    return MetricsIngestClient::instance();
}
