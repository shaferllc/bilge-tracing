<?php

declare(strict_types=1);

use Bilge\Tracing\Reporting\ErrorReportClient;

/**
 * Occurrence UUID from the last error report built by the Bilge tracing SDK (for user-feedback forms).
 *
 * @see ErrorReportClient::lastOccurrenceUuid()
 */
function bilge_last_error_occurrence_uuid(): ?string
{
    return ErrorReportClient::lastOccurrenceUuid();
}
