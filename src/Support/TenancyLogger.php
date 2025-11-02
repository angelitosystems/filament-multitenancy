<?php

namespace AngelitoSystems\FilamentTenancy\Support;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class TenancyLogger
{
    protected string $channel;
    protected array $defaultContext;

    public function __construct(string $channel = 'tenancy')
    {
        $this->channel = $channel;
        $this->defaultContext = [
            'timestamp' => now()->toISOString(),
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];
    }

    /**
     * Log tenant connection events.
     */
    public function logConnection(string $event, ?Tenant $tenant = null, array $context = []): void
    {
        $logData = [
            'event' => $event,
        ];

        if ($tenant) {
            $logData['tenant_id'] = $tenant->id;
            $logData['tenant_slug'] = $tenant->slug;
        }

        $this->log('info', "Tenant connection: {$event}", array_merge($logData, $context));
    }

    /**
     * Log tenant database operations.
     */
    public function logDatabaseOperation(string $operation, Tenant $tenant, array $context = []): void
    {
        $this->log('info', "Database operation: {$operation}", array_merge([
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'operation' => $operation,
        ], $context));
    }

    /**
     * Log credential operations.
     */
    public function logCredentialOperation(string $operation, ?Tenant $tenant = null, array $context = []): void
    {
        $logContext = [
            'operation' => $operation,
        ];

        if ($tenant) {
            $logContext['tenant_id'] = $tenant->id;
            $logContext['tenant_slug'] = $tenant->slug;
        }

        $this->log('info', "Credential operation: {$operation}", array_merge($logContext, $context));
    }

    /**
     * Log security events.
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->log('warning', "Security event: {$event}", array_merge([
            'event' => $event,
            'security_level' => 'high',
        ], $context));
    }

    /**
     * Log connection errors.
     */
    public function logConnectionError(string $error, ?Tenant $tenant = null, array $context = []): void
    {
        $logContext = [
            'error' => $error,
            'error_type' => 'connection',
        ];

        if ($tenant) {
            $logContext['tenant_id'] = $tenant->id;
            $logContext['tenant_slug'] = $tenant->slug;
        }

        $this->log('error', "Connection error: {$error}", array_merge($logContext, $context));
    }

    /**
     * Log performance metrics.
     */
    public function logPerformanceMetric(string $metric, float $value, array $context = []): void
    {
        $this->log('info', "Performance metric: {$metric}", array_merge([
            'metric' => $metric,
            'value' => $value,
            'unit' => $context['unit'] ?? 'ms',
        ], $context));
    }

    /**
     * Log tenant switching events.
     */
    public function logTenantSwitch(?Tenant $fromTenant, ?Tenant $toTenant): void
    {
        $context = [
            'from_tenant_id' => $fromTenant?->id,
            'from_tenant_slug' => $fromTenant?->slug,
            'to_tenant_id' => $toTenant?->id,
            'to_tenant_slug' => $toTenant?->slug,
        ];

        $this->log('info', 'Tenant context switched', $context);
    }

    /**
     * Log cache operations.
     */
    public function logCacheOperation(string $operation, string $key, array $context = []): void
    {
        $this->log('debug', "Cache operation: {$operation}", array_merge([
            'operation' => $operation,
            'cache_key' => $key,
        ], $context));
    }

    /**
     * Log configuration changes.
     */
    public function logConfigurationChange(string $key, $oldValue, $newValue, array $context = []): void
    {
        $this->log('info', "Configuration changed: {$key}", array_merge([
            'config_key' => $key,
            'old_value' => $this->maskSensitiveData($oldValue),
            'new_value' => $this->maskSensitiveData($newValue),
        ], $context));
    }

    /**
     * Log to the specified channel.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $fullContext = array_merge($this->defaultContext, $context);
        
        Log::channel($this->channel)->log($level, $message, $fullContext);
    }

    /**
     * Mask sensitive data in logs.
     */
    protected function maskSensitiveData($value): string
    {
        if (is_string($value) && strlen($value) > 0) {
            // Mask passwords, tokens, keys, etc.
            $sensitivePatterns = [
                'password',
                'token',
                'key',
                'secret',
                'credential',
            ];

            foreach ($sensitivePatterns as $pattern) {
                if (stripos($value, $pattern) !== false) {
                    return '***MASKED***';
                }
            }

            // Mask long strings that might be sensitive
            if (strlen($value) > 50) {
                return substr($value, 0, 10) . '***MASKED***';
            }
        }

        return (string) $value;
    }

    /**
     * Log credential errors.
     */
    public function logCredentialError(string $operation, ?Tenant $tenant = null, string $error = ''): void
    {
        $logContext = [
            'operation' => $operation,
            'error' => $error,
            'error_type' => 'credential',
        ];

        if ($tenant) {
            $logContext['tenant_id'] = $tenant->id;
            $logContext['tenant_slug'] = $tenant->slug;
        }

        $this->log('error', "Credential error: {$operation}", $logContext);
    }

    /**
     * Get log statistics.
     */
    public function getLogStatistics(int $hours = 24): array
    {
        // This would typically query a log storage system
        // For now, return a basic structure
        return [
            'period_hours' => $hours,
            'total_events' => 0,
            'connection_events' => 0,
            'error_events' => 0,
            'security_events' => 0,
            'performance_metrics' => [],
        ];
    }
}