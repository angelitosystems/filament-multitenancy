<?php

namespace AngelitoSystems\FilamentTenancy\Commands;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:migrate 
                            {--tenant= : Specific tenant ID to migrate (migrates all if not provided)}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Run seeders after migration}
                            {--force : Force the operation to run when in production}
                            {--step= : Number of migrations to rollback}
                            {--rollback : Rollback migrations}';

    /**
     * The console command description.
     */
    protected $description = 'Run migrations for tenant databases';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $fresh = $this->option('fresh');
        $seed = $this->option('seed');
        $force = $this->option('force');
        $step = $this->option('step');
        $rollback = $this->option('rollback');

        try {
            if ($tenantId) {
                // Migrate specific tenant
                $tenant = Tenancy::findTenant($tenantId);
                if (!$tenant) {
                    $this->error("Tenant with ID {$tenantId} not found.");
                    return self::FAILURE;
                }

                $this->migrateTenant($tenant, $fresh, $seed, $force, $step, $rollback);
            } else {
                // Migrate all tenants
                $tenants = Tenancy::getAllTenants();
                
                if ($tenants->isEmpty()) {
                    $this->info('No tenants found.');
                    return self::SUCCESS;
                }

                $this->info("Found {$tenants->count()} tenant(s). Starting migration...");

                foreach ($tenants as $tenant) {
                    $this->migrateTenant($tenant, $fresh, $seed, $force, $step, $rollback);
                }
            }

            $this->info('Migration completed successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Migrate a specific tenant.
     */
    protected function migrateTenant(
        Tenant $tenant, 
        bool $fresh = false, 
        bool $seed = false, 
        bool $force = false, 
        ?string $step = null, 
        bool $rollback = false
    ): void {
        $this->info("Migrating tenant: {$tenant->name} (ID: {$tenant->id})");

        Tenancy::runForTenant($tenant, function () use ($fresh, $seed, $force, $step, $rollback) {
            $migrationPaths = config('filament-tenancy.migrations.paths', [
                database_path('migrations/tenant'),
            ]);

            foreach ($migrationPaths as $path) {
                if (!is_dir($path)) {
                    continue;
                }

                $options = [
                    '--path' => $path,
                    '--database' => Tenancy::current()->getConnectionName(),
                ];

                if ($force) {
                    $options['--force'] = true;
                }

                if ($rollback) {
                    if ($step) {
                        $options['--step'] = $step;
                    }
                    Artisan::call('migrate:rollback', $options);
                    $this->line("  Rolled back migrations from: {$path}");
                } elseif ($fresh) {
                    $options['--force'] = true;
                    Artisan::call('migrate:fresh', $options);
                    $this->line("  Fresh migration from: {$path}");
                } else {
                    Artisan::call('migrate', $options);
                    $this->line("  Migrated from: {$path}");
                }
            }

            // Run seeders if requested
            if ($seed && !$rollback) {
                $seederPaths = config('filament-tenancy.seeders.paths', []);
                
                foreach ($seederPaths as $seederClass) {
                    if (class_exists($seederClass)) {
                        Artisan::call('db:seed', [
                            '--class' => $seederClass,
                            '--database' => Tenancy::current()->getConnectionName(),
                            '--force' => $force,
                        ]);
                        $this->line("  Seeded: {$seederClass}");
                    }
                }
            }
        });

        $this->info("  ✓ Completed migration for tenant: {$tenant->name}");
    }
}