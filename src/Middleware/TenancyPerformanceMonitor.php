<?php

namespace AngelitoSystems\FilamentTenancy\Middleware;

use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenancyPerformanceMonitor
{
    protected TenancyLogger $logger;

    public function __construct(TenancyLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!config('filament-tenancy.monitoring.performance.enabled', false)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startQueries = $this->getQueryCount();

        // Enable query logging if configured
        if (config('filament-tenancy.monitoring.performance.log_queries', false)) {
            DB::enableQueryLog();
        }

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endQueries = $this->getQueryCount();

        $metrics = [
            'execution_time' => ($endTime - $startTime) * 1000, // Convert to milliseconds
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'query_count' => $endQueries - $startQueries,
            'route' => $request->route()?->getName() ?? $request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
        ];

        // Log slow requests
        $slowThreshold = config('filament-tenancy.monitoring.performance.slow_request_threshold', 1000);
        if ($metrics['execution_time'] > $slowThreshold) {
            $this->logger->logPerformanceMetric('slow_request', $metrics['execution_time'], $metrics);
        }

        // Log high memory usage
        $memoryThreshold = config('filament-tenancy.monitoring.performance.high_memory_threshold', 50 * 1024 * 1024); // 50MB
        if ($metrics['memory_usage'] > $memoryThreshold) {
            $this->logger->logPerformanceMetric('high_memory_usage', $metrics['memory_usage'], $metrics);
        }

        // Log excessive queries
        $queryThreshold = config('filament-tenancy.monitoring.performance.excessive_queries_threshold', 50);
        if ($metrics['query_count'] > $queryThreshold) {
            $this->logger->logPerformanceMetric('excessive_queries', $metrics['query_count'], $metrics);
        }

        // Log all queries if enabled
        if (config('filament-tenancy.monitoring.performance.log_queries', false)) {
            $queries = DB::getQueryLog();
            if (!empty($queries)) {
                $this->logger->logPerformanceMetric('database_queries', count($queries), [
                    'queries' => $this->formatQueries($queries),
                    'total_time' => array_sum(array_column($queries, 'time')),
                ]);
            }
        }

        // Add performance headers if configured
        if (config('filament-tenancy.monitoring.performance.add_headers', false)) {
            $response->headers->set('X-Tenancy-Execution-Time', round($metrics['execution_time'], 2) . 'ms');
            $response->headers->set('X-Tenancy-Memory-Usage', $this->formatBytes($metrics['memory_usage']));
            $response->headers->set('X-Tenancy-Query-Count', $metrics['query_count']);
        }

        return $response;
    }

    /**
     * Get current query count.
     */
    protected function getQueryCount(): int
    {
        return collect(DB::getConnections())
            ->sum(fn($connection) => $connection->getQueryLog() ? count($connection->getQueryLog()) : 0);
    }

    /**
     * Format queries for logging.
     */
    protected function formatQueries(array $queries): array
    {
        return array_map(function ($query) {
            return [
                'sql' => $query['query'],
                'bindings' => $query['bindings'],
                'time' => $query['time'],
            ];
        }, $queries);
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