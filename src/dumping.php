<?php

declare(strict_types=1);

use Bilge\Tracing\Dump\DumpIngestClient;

if (! function_exists('bilge_dump')) {
    /**
     * Explicit, cross-language dump API: capture a value (as a normalized, redacted tree) to the
     * Bilge Dumps watcher. Returns the value so it can be used inline: {@code return bilge_dump($user);}
     *
     * @template T
     *
     * @param  T  $value
     * @return T
     */
    function bilge_dump(mixed $value, ?string $label = null): mixed
    {
        DumpIngestClient::instance()->capture($value, $label);

        return $value;
    }
}
