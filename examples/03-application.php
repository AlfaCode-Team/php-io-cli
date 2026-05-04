#!/usr/bin/env php
<?php

/**
 * php-io-cli — Example: Full CLI Application with Commands
 *
 * Demonstrates:
 *   - CLIApplication bootstrapping
 *   - Multiple commands with arguments / options
 *   - AbstractCommand output helpers
 *   - Interactive prompts inside commands
 *   - Progress bar + Shell integration pattern
 *
 * Run: php examples/03-application.php list
 *      php examples/03-application.php deploy --help
 *      php examples/03-application.php deploy staging
 *      php examples/03-application.php db:migrate
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AlfacodeTeam\PhpIoCli\AbstractCommand;
use AlfacodeTeam\PhpIoCli\CLIApplication;
use AlfacodeTeam\PhpIoCli\Depends\Colors;

// ── Commands ──────────────────────────────────────────────────────

/**
 * Simulated deployment command with full UI workflow.
 */
final class DeployCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'deploy';
        $this->description = 'Deploy the application to an environment';

        $this->addArgument('environment', 'Target environment', required: true);
        $this->addOption('tag', 't', 'Git tag to deploy', acceptsValue: true, default: 'latest');
        $this->addOption('dry-run', 'd', 'Simulate without side-effects');
        $this->addOption('force', 'f', 'Skip confirmation prompt');
    }

    protected function handle(): int
    {
        $env = (string) $this->argument('environment');
        $tag = (string) $this->option('tag', 'latest');
        $dryRun = $this->hasOption('dry-run');

        $this->section("Deployment: {$tag} → {$env}");

        if (!in_array($env, ['production', 'staging', 'development', 'local'], true)) {
            $this->error("Unknown environment: {$env}");

            return self::INVALID;
        }

        if ($env === 'production' && !$this->hasOption('force')) {
            $confirmed = $this->confirm('You are deploying to PRODUCTION. Are you sure?', false);
            if (!$confirmed) {
                $this->muted('Deployment cancelled.');

                return self::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->warning('DRY RUN — no changes will be made.');
        }

        // Simulate multi-step deployment with ProgressBar
        $steps = [
            'Pulling latest code',
            'Installing dependencies',
            'Running migrations',
            'Clearing caches',
            'Restarting services',
        ];

        $bar = $this->progressBar("Deploying to {$env}", count($steps));
        $bar->start();

        foreach ($steps as $step) {
            usleep(400_000); // 400ms simulated work
            $bar->advance(1, $step);
        }

        $bar->finish("Deployed {$tag} → {$env}");

        $this->alertSuccess('Deployment complete!', [
            "Environment: {$env}",
            "Tag: {$tag}",
            'Dry-run: ' . ($dryRun ? 'yes' : 'no'),
        ]);

        return self::SUCCESS;
    }
}

/**
 * Database migration command.
 */
final class MigrateCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'db:migrate';
        $this->description = 'Run pending database migrations';

        $this->addOption('rollback', 'r', 'Rollback the last batch');
        $this->addOption('steps', 's', 'Number of steps to roll back', acceptsValue: true, default: '1');
    }

    protected function handle(): int
    {
        $rollback = $this->hasOption('rollback');
        $steps = (int) $this->option('steps', '1');

        $this->section($rollback ? "Rolling back {$steps} migration(s)" : 'Running migrations');

        $migrations = $rollback
            ? array_reverse($this->fakeMigrations())
            : $this->fakeMigrations();

        if (empty($migrations)) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        $bar = $this->progressBar($rollback ? 'Rolling back' : 'Migrating', count($migrations));
        $bar->start();

        foreach ($migrations as $migration) {
            usleep(200_000);
            $bar->advance(1, $migration);
        }

        $bar->finish($rollback ? 'Rollback complete' : 'Migration complete');

        return self::SUCCESS;
    }

    private function fakeMigrations(): array
    {
        return [
            '2024_01_01_000001_create_users_table',
            '2024_01_02_000002_create_sessions_table',
            '2024_03_15_000003_add_role_to_users',
            '2024_06_20_000004_create_audit_log',
        ];
    }
}

/**
 * Interactive project scaffold command.
 */
final class MakeModuleCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'make:module';
        $this->description = 'Scaffold a new application module';
    }

    protected function handle(): int
    {
        $this->section('Module Generator');

        $name = $this->ask('Module name (kebab-case)', 'my-module');

        $features = (new AlfacodeTeam\PhpIoCli\Components\MultiSelect(
            'Select features to include',
            ['Controller', 'Repository', 'Service', 'Events', 'Tests', 'Migration', 'Factory'],
        ))->run();

        $this->newLine();
        $this->info("Creating module: {$name}");

        // Simulate file generation
        $spin = $this->spinner('Generating files');
        $spin->start();

        foreach ($features as $feature) {
            usleep(150_000);
            $spin->tick("Creating {$feature}…");
        }

        $spin->stop("Module '{$name}' created");

        // Show summary table
        $this->table()
            ->headers(['File', 'Status'])
            ->rows(array_map(
                static fn(string $f) => ["src/{$name}/{$f}.php", Colors::wrap('created', Colors::GREEN)],
                $features,
            ))
            ->render();

        $this->alertSuccess('Module created!', [
            "Name: {$name}",
            'Files: ' . count($features),
        ]);

        return self::SUCCESS;
    }
}

/**
 * List environment variables.
 */
final class EnvCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'env';
        $this->description = 'Display current environment variables';
        $this->addOption('filter', 'f', 'Filter by prefix', acceptsValue: true, default: '');
    }

    protected function handle(): int
    {
        $filter = (string) $this->option('filter', '');

        $vars = [
            ['APP_NAME',    'MyApplication'],
            ['APP_ENV',     'local'],
            ['APP_DEBUG',   'true'],
            ['DB_HOST',     'localhost'],
            ['DB_PORT',     '5432'],
            ['DB_DATABASE', 'myapp'],
            ['CACHE_DRIVER','redis'],
            ['QUEUE_DRIVER','database'],
        ];

        if ($filter !== '') {
            $vars = array_filter($vars, static fn($row) => str_starts_with($row[0], mb_strtoupper($filter)));
            $vars = array_values($vars);
        }

        if (empty($vars)) {
            $this->warning("No variables matching prefix: {$filter}");

            return self::SUCCESS;
        }

        $this->section('Environment Variables' . ($filter ? " (filter: {$filter})" : ''));

        $this->table()
            ->headers(['Variable', 'Value'])
            ->rows($vars)
            ->style('compact')
            ->render();

        return self::SUCCESS;
    }
}

// ── Bootstrap ─────────────────────────────────────────────────────

(new CLIApplication('MyPlatform CLI', '1.0.0'))
    ->add(
        new DeployCommand(),
        new MigrateCommand(),
        new MakeModuleCommand(),
        new EnvCommand(),
    )
    ->run();
