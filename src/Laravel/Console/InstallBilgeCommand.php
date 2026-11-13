<?php

declare(strict_types=1);

namespace Bilge\Tracing\Laravel\Console;

use Bilge\Tracing\Laravel\Install\BilgeProvisionClient;
use Bilge\Tracing\Support\BilgeDsn;
use Bilge\Tracing\Support\MonitoringEnv;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Writes {@code BILGE_DSN} (and optional {@code BILGE_LARAVEL}) to {@code .env}.
 *
 * Modes:
 * - Existing project: pass {@code --dsn=} or choose “existing DSN” when prompted.
 * - New project: use your Bilge API token (Profile → API tokens) plus the
 *   Bilge site URL; the command calls {@code GET /api/v1/me} and {@code POST /api/v1/projects}, then writes
 *   the new project’s ingest key as a DSN. Laravel is detected automatically (this command only runs in Laravel).
 */
final class InstallBilgeCommand extends Command
{
    protected $signature = 'bilge:install
                            {--dsn= : Full DSN, e.g. https://PROJECT_API_KEY@your-bilge.example.com}
                            {--url= : Bilge site base URL when creating a project (defaults to APP_URL when --token= is set non-interactively, e.g. self-hosting)}
                            {--token= : API token (Bearer) when creating a project; omit in interactive mode to be prompted}
                            {--organization= : Organization ULID when creating (required if you belong to multiple orgs, non-interactive)}
                            {--project-name= : New project name (defaults to config app.name)}
                            {--no-quick : Do not append BILGE_LARAVEL=true}';

    protected $description = 'Configure Bilge: append DSN to .env, or create a project via the Bilge API using an API token';

    public function handle(): int
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            $this->components->error('No .env file found. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        $dsn = trim((string) ($this->option('dsn') ?? ''));
        if ($dsn !== '') {
            return $this->installFromDsn($dsn, $envPath);
        }

        $url = trim((string) ($this->option('url') ?? ''));
        $token = trim((string) ($this->option('token') ?? ''));
        $organizationOption = trim((string) ($this->option('organization') ?? ''));
        $projectNameOption = trim((string) ($this->option('project-name') ?? ''));

        if ($token !== '' && $url === '' && ! $this->input->isInteractive()) {
            $url = $this->defaultProvisionBaseUrlCandidate();
            if ($url === '') {
                $this->components->error('Pass --url=… (your Bilge base URL, usually APP_URL for self-hosting) or set APP_URL when using --token= without --url.');

                return self::FAILURE;
            }
        }

        $useProvisioner = $url !== '' && $token !== '';
        if (! $useProvisioner && $this->input->isInteractive()) {
            $mode = select(
                label: 'How do you want to connect this Laravel app to Bilge?',
                options: [
                    'dsn' => 'I already have a project API key (DSN)',
                    'create' => 'Create a new project using my Bilge API token',
                ],
                default: 'create'
            );
            if ($mode === 'dsn') {
                $dsn = text(
                    label: 'Bilge DSN (https://YOUR_PROJECT_API_KEY@your-bilge-host.example.com)',
                    placeholder: 'https://…',
                    required: true
                );

                return $this->installFromDsn($dsn, $envPath);
            }
        }

        if (! $useProvisioner && ! $this->input->isInteractive()) {
            $this->components->error('Non-interactive: pass --dsn=… or both --url= and --token= to create a project.');

            return self::FAILURE;
        }

        if (! $useProvisioner) {
            $defaultUrl = $url !== '' ? $url : $this->defaultProvisionBaseUrlCandidate();
            $url = text(
                label: 'Bilge site URL (same origin you open in the browser, e.g. https://bilge.example.com)',
                placeholder: 'https://…',
                default: $defaultUrl,
                required: true,
                validate: fn (string $v): ?string => BilgeProvisionClient::normalizeOriginOrNull($v) !== null
                    ? null
                    : 'Enter a valid http(s) URL with a host.'
            );
            $token = password(
                label: 'API token (Bilge → Profile → API tokens)',
                placeholder: 'Token',
                required: true
            );
        }

        try {
            $client = BilgeProvisionClient::fromUserInput($url, $token);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Contacting Bilge…');
        $meResponse = $client->fetchMe();
        if ($meResponse === null) {
            $this->components->error('Could not load your Bilge profile (GET /api/v1/me). Check the URL and API token.');

            return self::FAILURE;
        }

        $organizations = $meResponse['organizations'] ?? [];
        if (! is_array($organizations) || $organizations === []) {
            $this->components->error('No organizations found for this account. Create or join an organization in Bilge first.');

            return self::FAILURE;
        }

        $organizationId = $organizationOption;
        if ($organizationId === '') {
            if (count($organizations) === 1) {
                $row = $organizations[0];
                $organizationId = is_array($row) ? (string) ($row['id'] ?? '') : '';
            } elseif ($this->input->isInteractive()) {
                $options = [];
                foreach ($organizations as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $id = (string) ($row['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $name = (string) ($row['name'] ?? $id);
                    $slug = (string) ($row['slug'] ?? '');
                    $options[$id] = $slug !== '' ? "{$name} ({$slug})" : $name;
                }
                if ($options === []) {
                    $this->components->error('Could not read organizations from the API response.');

                    return self::FAILURE;
                }
                $organizationId = select(
                    label: 'Which organization should own the new project?',
                    options: $options
                );
            } else {
                $this->components->error('You belong to multiple organizations. Pass --organization=ULID non-interactively.');

                return self::FAILURE;
            }
        }

        if ($organizationId === '' || ! Str::isUlid($organizationId)) {
            $this->components->error('Invalid or missing organization id (expected a ULID).');

            return self::FAILURE;
        }

        $allowedOrg = false;
        foreach ($organizations as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $organizationId) {
                $allowedOrg = true;
                break;
            }
        }
        if (! $allowedOrg) {
            $this->components->error('That organization is not available to this API token.');

            return self::FAILURE;
        }

        $projectName = $projectNameOption;
        if ($projectName === '') {
            $projectName = (string) config('app.name', '');
            $projectName = trim($projectName);
            if ($projectName === '') {
                $projectName = basename((string) base_path());
            }
        }
        if ($projectName === '') {
            $projectName = 'Laravel';
        }

        if ($this->input->isInteractive() && $projectNameOption === '') {
            $projectName = text(
                label: 'New Bilge project name',
                default: $projectName,
                required: true
            );
        }

        $techStacks = ['php', 'laravel'];

        $createResponse = $client->createProject($organizationId, $projectName, $techStacks);
        if (! $createResponse->successful()) {
            $this->reportProvisionFailure($createResponse);

            return self::FAILURE;
        }

        $data = $createResponse->json('data');
        if (! is_array($data)) {
            $this->components->error('Unexpected response when creating the project (missing data).');

            return self::FAILURE;
        }

        $apiKey = isset($data['api_key']) && is_string($data['api_key']) ? trim($data['api_key']) : '';
        if ($apiKey === '') {
            $this->components->error('Bilge did not return a project API key. Create a key from Project settings in the app, then run with --dsn=…');

            return self::FAILURE;
        }

        $dsnBuilt = BilgeDsn::fromApiKeyAndBaseUri($apiKey, $client->normalizedBaseUriForDsn());
        if ($dsnBuilt === null) {
            $this->components->error('Could not build a DSN from the new project key and base URL.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->warn('Copy the project API key now if you need it elsewhere; it may not be shown again in Bilge.');
        $this->line('  Project: '.$projectName);
        if (isset($data['id']) && is_string($data['id'])) {
            $this->line('  Id: '.$data['id']);
        }

        return $this->appendEnvAndFinish($dsnBuilt, $envPath);
    }

    private function installFromDsn(string $dsn, string $envPath): int
    {
        $parsed = BilgeDsn::parse($dsn);
        if ($parsed['api_key'] === null || $parsed['base_uri'] === null) {
            $this->components->error('Invalid DSN. Use: https://PROJECT_API_KEY@bilge-host.example.com (scheme + host required).');

            return self::FAILURE;
        }

        return $this->appendEnvAndFinish($dsn, $envPath);
    }

    private function appendEnvAndFinish(string $dsn, string $envPath): int
    {
        $body = File::get($envPath);
        $block = $this->formatEnvBlock($dsn, ! $this->option('no-quick'));

        if (preg_match('/^BILGE_DSN=/m', $body)) {
            $this->components->warn('BILGE_DSN is already present in .env; leaving the file unchanged. Edit .env manually or remove the existing line first.');

            return self::SUCCESS;
        }

        File::append($envPath, $block);
        $this->components->info('Bilge variables were appended to .env.');
        if (! $this->option('no-quick')) {
            $this->line('  • BILGE_LARAVEL=true enables monitoring client toggles (see packages/bilge-tracing/src/Support/MonitoringEnv.php).');
        }
        $this->line('  • Optional: publish config with php artisan vendor:publish --tag=bilge-tracing-config');

        return self::SUCCESS;
    }

    /**
     * First normalized URL from services.bilge.url, then APP_URL — for pre-filling prompts and self-hosted installs.
     */
    private function defaultProvisionBaseUrlCandidate(): string
    {
        foreach ([config('services.bilge.url'), config('app.url')] as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $normalized = BilgeProvisionClient::normalizeOriginOrNull($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return '';
    }

    private function reportProvisionFailure(Response $response): void
    {
        $message = $response->json('message');
        if (is_string($message) && $message !== '') {
            $this->components->error($message);
            $billingUrl = $response->json('billing_url');
            if (is_string($billingUrl) && $billingUrl !== '') {
                $this->newLine();
                $this->components->twoColumnDetail('Billing', $billingUrl);
            }

            return;
        }
        $errors = $response->json('errors');
        if (is_array($errors) && $errors !== []) {
            $this->components->error('Validation failed: '.json_encode($errors));

            return;
        }
        $this->components->error('Could not create the project (HTTP '.$response->status().').');
    }

    /**
     * @return non-empty-string
     */
    private function formatEnvBlock(string $dsn, bool $includeLaravelQuick): string
    {
        $escaped = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\n', '\r'], $dsn);
        $lines = [
            '',
            '# Bilge (bilgesh/tracing package)',
            'BILGE_DSN="'.$escaped.'"',
        ];
        if ($includeLaravelQuick) {
            $lines = array_merge($lines, MonitoringEnv::quickStartEnvLines());
        }

        return implode("\n", $lines)."\n";
    }
}
