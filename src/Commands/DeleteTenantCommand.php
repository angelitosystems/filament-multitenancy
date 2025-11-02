<?php

namespace AngelitoSystems\FilamentTenancy\Commands;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use Illuminate\Console\Command;

class DeleteTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:delete 
                            {tenant : The tenant ID or slug to delete}
                            {--force : Force deletion without confirmation}
                            {--keep-database : Keep the tenant database (do not delete)}';

    /**
     * The console command description.
     */
    protected $description = 'Delete a tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantIdentifier = $this->argument('tenant');
        $force = $this->option('force');
        $keepDatabase = $this->option('keep-database');

        try {
            // Find tenant by ID or slug
            $tenant = null;
            if (is_numeric($tenantIdentifier)) {
                $tenant = Tenancy::findTenant((int) $tenantIdentifier);
            } else {
                $tenant = Tenancy::findTenantBySlug($tenantIdentifier);
            }

            if (!$tenant) {
                $this->error("Tenant '{$tenantIdentifier}' not found.");
                return self::FAILURE;
            }

            // Show tenant information
            $this->info("Tenant to delete:");
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $tenant->id],
                    ['Name', $tenant->name],
                    ['Slug', $tenant->slug],
                    ['Domain', $tenant->domain ?: 'N/A'],
                    ['Subdomain', $tenant->subdomain ?: 'N/A'],
                    ['Database', $tenant->database_name],
                    ['Active', $tenant->is_active ? 'Yes' : 'No'],
                    ['Created', $tenant->created_at->format('Y-m-d H:i:s')],
                ]
            );

            // Confirmation
            if (!$force) {
                $databaseAction = $keepDatabase ? 'kept' : 'deleted';
                $confirmed = $this->confirm(
                    "Are you sure you want to delete tenant '{$tenant->name}'? " .
                    "The tenant database will be {$databaseAction}."
                );

                if (!$confirmed) {
                    $this->info('Deletion cancelled.');
                    return self::SUCCESS;
                }
            }

            // Temporarily override database deletion setting if --keep-database is used
            $originalSetting = config('filament-tenancy.database.auto_delete_tenant_database');
            if ($keepDatabase) {
                config(['filament-tenancy.database.auto_delete_tenant_database' => false]);
            }

            // Delete tenant
            $this->info('Deleting tenant...');
            $deleted = Tenancy::deleteTenant($tenant);

            // Restore original setting
            if ($keepDatabase) {
                config(['filament-tenancy.database.auto_delete_tenant_database' => $originalSetting]);
            }

            if ($deleted) {
                $this->info("Tenant '{$tenant->name}' deleted successfully!");
                
                if ($keepDatabase) {
                    $this->warn("Database '{$tenant->database_name}' was kept as requested.");
                } elseif (config('filament-tenancy.database.auto_delete_tenant_database')) {
                    $this->info("Database '{$tenant->database_name}' was also deleted.");
                }
            } else {
                $this->error('Failed to delete tenant.');
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to delete tenant: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}