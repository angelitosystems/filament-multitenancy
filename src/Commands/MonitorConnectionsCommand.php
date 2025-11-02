<?php

namespace AngelitoSystems\FilamentTenancy\Commands;

use AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorConnectionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:monitor-connections 
                            {--interval=30 : Monitoring interval in seconds}
                            {--duration=300 : Total monitoring duration in seconds}
                            {--output=console : Output format (console|json|log)}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor tenant database connections and performance metrics';

    protected ConnectionManagerInterface $connectionManager;
    protected TenancyLogger $logger;

    public function __construct(ConnectionManagerInterface $connectionManager, TenancyLogger $logger)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
        $this->logger = $logger;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $duration = (int) $this->option('duration');
        $output = $this->option('output');

        $this->info("Starting connection monitoring for {$duration} seconds with {$interval}s intervals...");

        $startTime = time();
        $endTime = $startTime + $duration;

        while (time() < $endTime) {
            $metrics = $this->collectMetrics();
            
            $this->displayMetrics($metrics, $output);
            
            if (time() + $interval < $endTime) {
                sleep($interval);
            }
        }

        $this->info('Connection monitoring completed.');
        return 0;
    }

    /**
     * Collect connection metrics.
     */
    protected function collectMetrics(): array
    {
        $connections = DB::getConnections();
        $metrics = [
            'timestamp' => now()->toISOString(),
            'total_connections' => count($connections),
            'active_connections' => [],
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        foreach ($connections as $name => $connection) {
            try {
                $pdo = $connection->getPdo();
                $metrics['active_connections'][$name] = [
                    'status' => 'active',
                    'driver' => $connection->getDriverName(),
                    'database' => $connection->getDatabaseName(),
                    'query_count' => count($connection->getQueryLog()),
                ];
            } catch (\Exception $e) {
                $metrics['active_connections'][$name] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $metrics;
    }

    /**
     * Display metrics based on output format.
     */
    protected function displayMetrics(array $metrics, string $output): void
    {
        switch ($output) {
            case 'json':
                $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
                break;
                
            case 'log':
                $this->logger->logPerformanceMetric('connection_monitoring', count($metrics['active_connections']), $metrics);
                break;
                
            default: // console
                $this->displayConsoleMetrics($metrics);
                break;
        }
    }

    /**
     * Display metrics in console format.
     */
    protected function displayConsoleMetrics(array $metrics): void
    {
        $this->info("=== Connection Metrics - {$metrics['timestamp']} ===");
        $this->line("Total Connections: {$metrics['total_connections']}");
        $this->line("Memory Usage: " . $this->formatBytes($metrics['memory_usage']));
        $this->line("Peak Memory: " . $this->formatBytes($metrics['peak_memory']));
        
        if (!empty($metrics['active_connections'])) {
            $this->line("\nActive Connections:");
            
            $headers = ['Name', 'Status', 'Driver', 'Database', 'Queries'];
            $rows = [];
            
            foreach ($metrics['active_connections'] as $name => $info) {
                $rows[] = [
                    $name,
                    $info['status'],
                    $info['driver'] ?? 'N/A',
                    $info['database'] ?? 'N/A',
                    $info['query_count'] ?? 'N/A',
                ];
            }
            
            $this->table($headers, $rows);
        }
        
        $this->line(str_repeat('-', 50));
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}