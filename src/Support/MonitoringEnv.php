<?php

declare(strict_types=1);

namespace Bilge\Tracing\Support;

/**
 * Central catalog of Bilge SDK monitoring env toggles (client-side).
 *
 * Each UI watcher maps to one or more env vars. Project Settings → Monitoring modes
 * must also allow the matching {@code *_ingest_enabled} gate on the server.
 */
final class MonitoringEnv
{
    /**
     * Resolve a boolean env var: explicit value wins; otherwise use the Laravel quick-start default.
     */
    public static function resolveEnabled(mixed $raw, bool $laravelQuickStartDefault): bool
    {
        if ($raw !== null && $raw !== '') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        return $laravelQuickStartDefault;
    }

    /**
     * Lines appended by {@code php artisan bilge:install} when quick start is enabled.
     *
     * @return list<string>
     */
    public static function quickStartEnvLines(): array
    {
        return [
            'BILGE_LARAVEL=true',
            '',
            '# Bilge monitoring (SDK client toggles — also enable ingest in Project → Settings → Monitoring modes)',
            'BILGE_REPORT_EXCEPTIONS=true',
            'BILGE_REPORT_HTTP_404=true',
            'BILGE_TRACING_AUTO_FLUSH=true',
            '',
            '# Dedicated ingest watchers (Telescope-style)',
            'BILGE_JOB_MONITORING_ENABLED=true',
            'BILGE_MAIL_MONITORING_ENABLED=true',
            'BILGE_EVENT_MONITORING_ENABLED=true',
            'BILGE_NOTIFICATION_MONITORING_ENABLED=true',
            'BILGE_MODEL_MONITORING_ENABLED=true',
            'BILGE_CRON_MONITORING_ENABLED=true',
            '',
            '# Trace-derived watchers (Requests, Traces, Queries, Cache, HTTP client, Commands, Queues)',
            'BILGE_PERFORMANCE_ENABLED=true',
            'BILGE_PERFORMANCE_AUTO_MIDDLEWARE=true',
            'BILGE_PERFORMANCE_SAMPLE_RATE=0.1',
            'BILGE_PERFORMANCE_COLLECT_HTTP=true',
            'BILGE_PERFORMANCE_COLLECT_DB=true',
            'BILGE_PERFORMANCE_COLLECT_HTTP_CLIENT=true',
            'BILGE_PERFORMANCE_COLLECT_CACHE=true',
            'BILGE_PERFORMANCE_COLLECT_CONSOLE=true',
            'BILGE_PERFORMANCE_COLLECT_QUEUE=true',
            'BILGE_PERFORMANCE_COLLECT_REDIS=false',
            'BILGE_PERFORMANCE_COLLECT_VIEW=false',
            '',
            '# Structured logs + custom metrics',
            'BILGE_LOGS_ENABLED=true',
            'BILGE_METRICS_ENABLED=true',
            '',
            '# Profiling (auto-profiles only when the Excimer extension is installed)',
            'BILGE_PROFILING_ENABLED=false',
            'BILGE_PROFILING_SAMPLE_RATE=0.05',
            '# Cooperative pulse sampler — opt in only for deliberate bilge_profiles() use; off by default',
            '# BILGE_PROFILING_MANUAL_PULSE_FALLBACK=true',
            '',
            '# Browser RUM (Web Vitals + Livewire navigations + interaction timers)',
            'BILGE_RUM_ENABLED=true',
            'BILGE_RUM_LIVEWIRE_NAVIGATE=true',
        ];
    }

    /**
     * Reference catalog: UI area → primary SDK env var(s).
     *
     * @return array<string, array{
     *     label: string,
     *     primary_env: string,
     *     related_env: list<string>,
     *     server_gate: string|null,
     *     quick_start: bool
     * }>
     */
    public static function catalog(): array
    {
        return [
            'issues' => [
                'label' => 'Issues (errors)',
                'primary_env' => 'BILGE_REPORT_EXCEPTIONS',
                'related_env' => ['BILGE_REPORT_HTTP_404', 'BILGE_REPORT_SAMPLE_RATE'],
                'server_gate' => null,
                'quick_start' => true,
            ],
            'requests' => [
                'label' => 'Requests',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => [
                    'BILGE_PERFORMANCE_COLLECT_HTTP',
                    'BILGE_PERFORMANCE_AUTO_MIDDLEWARE',
                    'BILGE_TRACING_AUTO_FLUSH',
                    'BILGE_PERFORMANCE_SAMPLE_RATE',
                ],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'jobs' => [
                'label' => 'Queues',
                'primary_env' => 'BILGE_JOB_MONITORING_ENABLED',
                'related_env' => ['BILGE_PERFORMANCE_COLLECT_QUEUE'],
                'server_gate' => 'job_ingest_enabled',
                'quick_start' => true,
            ],
            'commands' => [
                'label' => 'Commands',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => [
                    'BILGE_PERFORMANCE_COLLECT_CONSOLE',
                    'BILGE_PERFORMANCE_FLUSH_CLI_QUEUE',
                ],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'schedule' => [
                'label' => 'Schedule / crons',
                'primary_env' => 'BILGE_CRON_MONITORING_ENABLED',
                'related_env' => [],
                'server_gate' => null,
                'quick_start' => true,
            ],
            'logs' => [
                'label' => 'Logs',
                'primary_env' => 'BILGE_LOGS_ENABLED',
                'related_env' => ['BILGE_LOGS_FLUSH_ON_TERMINATE', 'BILGE_LOGS_MAX_BUFFER'],
                'server_gate' => 'log_ingest_enabled',
                'quick_start' => true,
            ],
            'mail' => [
                'label' => 'Mail',
                'primary_env' => 'BILGE_MAIL_MONITORING_ENABLED',
                'related_env' => [],
                'server_gate' => 'mail_ingest_enabled',
                'quick_start' => true,
            ],
            'events' => [
                'label' => 'Events',
                'primary_env' => 'BILGE_EVENT_MONITORING_ENABLED',
                'related_env' => ['BILGE_EVENT_MONITORING_WILDCARD', 'BILGE_EVENT_MONITORING_SAMPLE_EVERY'],
                'server_gate' => 'event_ingest_enabled',
                'quick_start' => true,
            ],
            'notifications' => [
                'label' => 'Notifications',
                'primary_env' => 'BILGE_NOTIFICATION_MONITORING_ENABLED',
                'related_env' => [],
                'server_gate' => 'notification_ingest_enabled',
                'quick_start' => true,
            ],
            'models' => [
                'label' => 'Models',
                'primary_env' => 'BILGE_MODEL_MONITORING_ENABLED',
                'related_env' => ['BILGE_MODEL_MONITORING_NAMESPACE'],
                'server_gate' => 'model_ingest_enabled',
                'quick_start' => true,
            ],
            'gates' => [
                'label' => 'Gates',
                'primary_env' => 'BILGE_GATE_MONITORING_ENABLED',
                'related_env' => [],
                'server_gate' => 'gate_ingest_enabled',
                'quick_start' => true,
            ],
            'database' => [
                'label' => 'Queries',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => [
                    'BILGE_PERFORMANCE_COLLECT_DB',
                    'BILGE_PERFORMANCE_SLOW_QUERY_MS',
                    'BILGE_PERFORMANCE_QUERY_INSIGHTS',
                ],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'redis' => [
                'label' => 'Redis',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => ['BILGE_PERFORMANCE_COLLECT_REDIS'],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => false,
            ],
            'cache' => [
                'label' => 'Cache',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => ['BILGE_PERFORMANCE_COLLECT_CACHE'],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'http_client' => [
                'label' => 'HTTP client',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => ['BILGE_PERFORMANCE_COLLECT_HTTP_CLIENT'],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'traces' => [
                'label' => 'Traces',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => ['BILGE_TRACING_AUTO_FLUSH', 'BILGE_PERFORMANCE_SAMPLE_RATE'],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'transactions' => [
                'label' => 'Transactions',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => ['BILGE_PERFORMANCE_TAIL_SAMPLING'],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'profiles' => [
                'label' => 'Profiles',
                'primary_env' => 'BILGE_PROFILING_ENABLED',
                'related_env' => [
                    'BILGE_PROFILING_SAMPLE_RATE',
                    'BILGE_PROFILING_MANUAL_PULSE_FALLBACK',
                    'BILGE_PERFORMANCE_ENABLED',
                ],
                'server_gate' => 'profiling_ingest_enabled',
                'quick_start' => false,
            ],
            'metrics' => [
                'label' => 'Metrics',
                'primary_env' => 'BILGE_METRICS_ENABLED',
                'related_env' => ['BILGE_METRICS_FLUSH_ON_TERMINATE', 'BILGE_METRICS_MAX_BUFFER'],
                'server_gate' => 'metrics_ingest_enabled',
                'quick_start' => true,
            ],
            'rum' => [
                'label' => 'RUM',
                'primary_env' => 'BILGE_RUM_ENABLED',
                'related_env' => ['BILGE_RUM_LIVEWIRE_NAVIGATE', 'BILGE_RUM_INGEST_PATH'],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => true,
            ],
            'releases' => [
                'label' => 'Releases',
                'primary_env' => 'BILGE_RELEASE',
                'related_env' => ['BILGE_COMMIT_SHA', 'BILGE_DEPLOYED_AT'],
                'server_gate' => null,
                'quick_start' => false,
            ],
            'views' => [
                'label' => 'Views',
                'primary_env' => 'BILGE_PERFORMANCE_ENABLED',
                'related_env' => ['BILGE_PERFORMANCE_COLLECT_VIEW'],
                'server_gate' => 'performance_ingest_enabled',
                'quick_start' => false,
            ],
        ];
    }
}
