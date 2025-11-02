<?php

namespace AngelitoSystems\FilamentTenancy\Commands;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use Illuminate\Console\Command;

class ListTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:list 
                            {--active : Show only active tenants}
                            {--expired : Show only expired tenants}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'List all tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $activeOnly = $this->option('active');
        $expiredOnly = $this->option('expired');
        $format = $this->option('format');

        try {
            $tenants = Tenancy::runForCentral(function () use ($activeOnly, $expiredOnly) {
                $query = \AngelitoSystems\FilamentTenancy\Models\Tenant::query();

                if ($activeOnly) {
                    $query->active();
                } elseif ($expiredOnly) {
                    $query->expired();
                }

                return $query->orderBy('created_at', 'desc')->get();
            });

            if ($tenants->isEmpty()) {
                $this->info('No tenants found.');
                return self::SUCCESS;
            }

            if ($format === 'json') {
                $this->line($tenants->toJson(JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            // Table format
            $headers = ['ID', 'Name', 'Slug', 'Domain', 'Subdomain', 'Database', 'Active', 'Plan', 'Expires', 'Created'];
            $rows = [];

            foreach ($tenants as $tenant) {
                $rows[] = [
                    $tenant->id,
                    $tenant->name,
                    $tenant->slug,
                    $tenant->domain ?: '-',
                    $tenant->subdomain ?: '-',
                    $tenant->database_name,
                    $tenant->is_active ? '✓' : '✗',
                    $tenant->plan ?: '-',
                    $tenant->expires_at ? $tenant->expires_at->format('Y-m-d') : 'Never',
                    $tenant->created_at->format('Y-m-d H:i'),
                ];
            }

            $this->table($headers, $rows);
            $this->info("Total tenants: {$tenants->count()}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to list tenants: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}