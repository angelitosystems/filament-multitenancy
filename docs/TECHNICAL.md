# Technical Documentation - Filament Tenancy

## Architecture Overview

The Filament Tenancy package is built with a modular architecture that provides comprehensive multi-tenancy support with advanced logging and monitoring capabilities.

### Core Components

#### 1. Connection Management
- **ConnectionManager**: Manages database connections for tenants
- **CredentialManager**: Handles secure credential storage and rotation
- **Connection Pooling**: Optimizes database connection usage

#### 2. Logging System
- **TenancyLogger**: Centralized logging for all tenancy operations
- **Event Tracking**: Comprehensive audit trails
- **Performance Metrics**: Real-time monitoring data

#### 3. Monitoring System
- **Performance Monitoring**: Tracks connection times and resource usage
- **Alert System**: Configurable thresholds and notifications
- **Health Checks**: Automated system health monitoring

## Class Structure

### Models

#### Tenant Model
```php
AngelitoSystems\FilamentTenancy\Models\Tenant
```

**Fillable Attributes:**
- `name`: Tenant display name
- `slug`: URL-friendly identifier
- `domain`: Primary domain
- `subdomain`: Subdomain identifier
- `database_name`: Tenant database name
- `database_host`: Database host
- `database_port`: Database port
- `database_username`: Database username
- `database_password`: Database password (encrypted)
- `is_active`: Tenant status
- `plan`: Subscription plan
- `expires_at`: Expiration date
- `data`: Additional JSON data

**Casts:**
- `expires_at`: datetime
- `data`: array
- `is_active`: boolean

### Support Classes

#### ConnectionManager
```php
AngelitoSystems\FilamentTenancy\Support\ConnectionManager
```

**Key Methods:**
- `switchToTenant(Tenant $tenant)`: Switch to tenant database
- `switchToCentral()`: Switch back to central database
- `validateTenantConfiguration(array $config)`: Validate tenant config
- `getTenantDatabaseConfig(Tenant $tenant)`: Get database configuration

#### CredentialManager
```php
AngelitoSystems\FilamentTenancy\Support\CredentialManager
```

**Key Methods:**
- `storeCredentials(string $profile, array $credentials)`: Store encrypted credentials
- `getCredentials(string $profile)`: Retrieve decrypted credentials
- `rotateEncryptionKeys()`: Rotate encryption keys
- `validateCredentials(array $credentials)`: Validate credential format

#### TenancyLogger
```php
AngelitoSystems\FilamentTenancy\Support\TenancyLogger
```

**Key Methods:**
- `logTenantConnection(Tenant $tenant, string $action)`: Log connection events
- `logDatabaseOperation(string $operation, Tenant $tenant, array $context)`: Log DB operations
- `logCredentialOperation(string $operation, Tenant $tenant, array $context)`: Log credential operations
- `logSecurityEvent(string $event, array $context)`: Log security events
- `logPerformanceMetric(string $metric, float $value, array $context)`: Log performance data

### Contracts (Interfaces)

#### ConnectionManagerInterface
```php
AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface
```

#### CredentialManagerInterface
```php
AngelitoSystems\FilamentTenancy\Support\Contracts\CredentialManagerInterface
```

### Exceptions

#### ConnectionException
```php
AngelitoSystems\FilamentTenancy\Support\Exceptions\ConnectionException
```

## Database Schema

### Tenants Table

```sql
CREATE TABLE tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    domain VARCHAR(255) NULL,
    subdomain VARCHAR(255) NULL,
    database_name VARCHAR(255) NULL,
    database_host VARCHAR(255) NULL,
    database_port INT NULL,
    database_username VARCHAR(255) NULL,
    database_password TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    plan VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    data JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_tenants_slug (slug),
    INDEX idx_tenants_domain (domain),
    INDEX idx_tenants_subdomain (subdomain),
    INDEX idx_tenants_is_active (is_active),
    INDEX idx_tenants_expires_at (expires_at)
);
```

## Configuration

### Environment Variables

```env
# Database Configuration
TENANCY_AUTO_CREATE_DB=true
TENANCY_AUTO_DELETE_DB=false
TENANCY_CONNECTION_POOL_SIZE=10
TENANCY_DB_DRIVER=mysql
TENANCY_CONNECTION_TIMEOUT=30

# Logging Configuration
TENANCY_LOGGING_ENABLED=true
TENANCY_LOG_CHANNEL=tenancy
TENANCY_LOG_LEVEL=info
TENANCY_MASK_SENSITIVE_DATA=true

# Monitoring Configuration
TENANCY_MONITORING_ENABLED=true
TENANCY_PERFORMANCE_THRESHOLD=1000
TENANCY_MEMORY_THRESHOLD=128

# Security Configuration
TENANCY_ENCRYPTION_KEY=your-encryption-key
TENANCY_CREDENTIAL_ROTATION_DAYS=90
TENANCY_MAX_LOGIN_ATTEMPTS=3

# Cache Configuration
TENANCY_CACHE_ENABLED=true
TENANCY_CACHE_STORE=redis
TENANCY_CACHE_TTL=3600
```

## Performance Considerations

### Connection Pooling

The package implements connection pooling to optimize database connections:

- **Pool Size**: Configurable maximum connections per tenant
- **Connection Reuse**: Automatic connection reuse for efficiency
- **Cleanup**: Automatic cleanup of idle connections
- **Monitoring**: Real-time connection pool monitoring

### Caching Strategy

- **Tenant Configuration**: Cached for fast access
- **Credentials**: Encrypted and cached with TTL
- **Performance Metrics**: Cached for dashboard display
- **Connection Pool Status**: Real-time cache updates

### Memory Management

- **Connection Limits**: Configurable per-tenant connection limits
- **Memory Monitoring**: Automatic memory usage tracking
- **Garbage Collection**: Automatic cleanup of unused resources
- **Alert Thresholds**: Configurable memory usage alerts

## Security Features

### Credential Encryption

All database credentials are encrypted using Laravel's encryption system:

```php
// Automatic encryption when storing
$credentialManager->storeCredentials('profile', [
    'password' => 'plain-text-password'
]);

// Automatic decryption when retrieving
$credentials = $credentialManager->getCredentials('profile');
// $credentials['password'] is now decrypted
```

### Data Masking

Sensitive data is automatically masked in logs:

```php
// Original data
$credentials = ['password' => 'secret123'];

// Logged as
$loggedData = ['password' => '***masked***'];
```

### Access Control

- **Tenant Isolation**: Complete database isolation between tenants
- **Connection Validation**: Automatic validation of tenant access
- **Audit Trails**: Complete logging of all operations
- **Security Events**: Automatic logging of security-related events

## Testing

### Test Structure

```
tests/
├── Unit/                    # Unit tests for individual components
│   ├── TenantTest.php      # Tenant model tests
│   ├── ConnectionManagerTest.php
│   ├── CredentialManagerTest.php
│   └── TenancyLoggerTest.php
├── Integration/             # Integration tests
│   ├── DatabaseConnectionTest.php
│   ├── LoggingIntegrationTest.php
│   └── MonitoringIntegrationTest.php
└── Feature/                 # Feature tests
    ├── TenantManagementTest.php
    ├── PerformanceMonitoringTest.php
    └── SecurityTest.php
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/phpunit tests/Unit/
vendor/bin/phpunit tests/Integration/
vendor/bin/phpunit tests/Feature/

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Test Configuration

The package includes a simplified test configuration:

```php
// tests/TestCase.php
class TestCase extends Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            FilamentTenancyServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        // Additional test configuration
    }
}
```

## Monitoring and Alerts

### Performance Metrics

The system tracks various performance metrics:

- **Connection Time**: Time to establish database connections
- **Query Execution Time**: Database query performance
- **Memory Usage**: Application memory consumption
- **Active Connections**: Number of active database connections

### Alert System

Configurable alerts for:

- **Slow Connections**: Connections exceeding threshold time
- **High Memory Usage**: Memory usage above configured limits
- **Failed Connections**: Connection failures and errors
- **Security Events**: Unauthorized access attempts

### Monitoring Commands

```bash
# Monitor active connections
php artisan tenancy:monitor-connections

# Monitor with specific parameters
php artisan tenancy:monitor-connections --interval=60 --duration=600

# Output formats
php artisan tenancy:monitor-connections --format=json
php artisan tenancy:monitor-connections --format=log
```

## Troubleshooting

### Common Issues

1. **Connection Timeouts**
   - Check database server availability
   - Verify network connectivity
   - Adjust connection timeout settings

2. **Memory Issues**
   - Monitor connection pool sizes
   - Check for memory leaks
   - Adjust memory thresholds

3. **Performance Issues**
   - Review slow query logs
   - Optimize database indexes
   - Adjust connection pool settings

### Debug Mode

Enable debug logging for detailed troubleshooting:

```php
'logging' => [
    'level' => 'debug',
    'enabled' => true,
],
```

### Log Analysis

Monitor log files for:

- Connection patterns
- Performance bottlenecks
- Security events
- Error patterns

## Extension Points

### Custom Credential Managers

Implement custom credential management:

```php
class CustomCredentialManager implements CredentialManagerInterface
{
    // Implement interface methods
}
```

### Custom Loggers

Extend logging functionality:

```php
class CustomTenancyLogger extends TenancyLogger
{
    // Override or extend methods
}
```

### Custom Monitoring

Add custom monitoring metrics:

```php
class CustomMonitor
{
    public function collectMetrics()
    {
        // Custom metric collection
    }
}
```