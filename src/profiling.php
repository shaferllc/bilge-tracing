<?php

declare(strict_types=1);

use Bilge\Tracing\Profiling\ProfileIngestClient;

/**
 * Bilge profiling entry point:
 * {@code bilge_profiles()->time('import.csv', fn () => runImport(), ['rows' => 100]);}
 * {@code bilge_profiles()->start('checkout.submit', ['step' => 'payment'])->stop(['ok' => true]);}
 * {@code bilge_profiles()->profile('aggregate')->sendAggregate([...]);}
 */
function bilge_profiles(): ProfileIngestClient
{
    return ProfileIngestClient::instance();
}
