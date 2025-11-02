# Changelog

All notable changes to the Filament Tenancy package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Interactive Installer Command** (`filament-tenancy:install`):
  - Automatic Filament installation check and setup
  - Database compatibility verification (MySQL/PostgreSQL)
  - Interactive database configuration wizard
  - Automatic configuration file publishing
  - ServiceProvider auto-registration (Laravel 10 & 11)
  - Smart migration execution with retry logic
  - Installation cleanup on critical errors
  - Connection testing after database configuration

- **Interactive Tenant Creation** (`tenancy:create`):
  - Beautiful branded interface
  - Step-by-step interactive wizard
  - Domain or subdomain selection
  - Plan selection with predefined options
  - Database name auto-generation
  - Validation and error handling
  - **APP_DOMAIN Auto-Detection**: Automatically detects and configures `APP_DOMAIN` from `APP_URL`
  - **Smart Domain Detection**: Detects valid domains from `APP_URL` and suggests configuration
  - **Environment File Updates**: Automatically updates `.env` file with `APP_DOMAIN` when configured
  - **Localhost/Port Detection**: Prompts user to configure `APP_DOMAIN` when `APP_URL` is localhost or has a port

- **Plan and Subscription Models**:
  - `Plan` model for managing subscription plans
  - `Subscription` model for tenant subscriptions
  - Support for plan features and limits (JSON)
  - Subscription status management (active, canceled, expired, trial)
  - Automatic expiration handling
  - Relationship with Tenant model

- **Plan Seeder**:
  - **Automatic Plan Creation**: `PlanSeeder` automatically creates default plans (Basic, Premium, Enterprise) during installation
  - **Seeder Publishing**: PlanSeeder is automatically published to `database/seeders/PlanSeeder.php` during installation
  - **Customizable Plans**: Published seeder can be customized to modify plans or add new ones
  - **Auto-Execution**: Seeder runs automatically after migrations during installation
  - **Database-Driven Plans**: Tenant creation now loads plans from database instead of hardcoded values

- **Automatic Subscription Creation**:
  - **Subscription Auto-Creation**: When creating a tenant with a plan, a subscription is automatically created
  - **Plan Selection**: Interactive tenant creation shows real plans from database with prices and billing cycles
  - **Active Subscription**: New subscriptions are created with `active` status and `starts_at` timestamp
  - **Subscription Display**: Tenant creation output shows subscription status

- **Enhanced Error Handling**:
  - Connection error detection and retry mechanism (up to 3 attempts)
  - Automatic installation cleanup on critical failures
  - Better error messages and user guidance
  - SQLite compatibility warnings

- **Database Compatibility**:
  - Automatic detection of incompatible databases (SQLite)
  - Interactive MySQL/PostgreSQL configuration
  - Connection testing with detailed feedback
  - Environment file (.env) automatic updates

- **Domain and URL Management**:
  - **DomainResolver Class**: Internal class for resolving base domains from APP_DOMAIN, APP_URL, or config
  - **TenantUrlGenerator Class**: Internal class for generating tenant URLs with proper protocol handling
  - Smart domain extraction that removes subdomains when needed
  - Automatic fallback chain for domain resolution

- **Model Architecture Refactoring**:
  - **TenantCore Class**: Internal abstract class (`src/Models/Core/TenantCore.php`) containing all business logic
  - **Tenant Model**: Public model that extends TenantCore, now contains only Eloquent-specific configuration
  - Clean separation of concerns: fillable, casts, dates, and relationships in Tenant; all business logic in TenantCore
  - Improved code maintainability and extensibility
  - Better organization of internal package structure

### Added
- **Advanced Logging System**: Comprehensive logging for all tenancy operations
  - Tenant connection tracking
  - Database operation logging
  - Credential operation auditing
  - Security event monitoring
  - Performance metrics collection
  - Configuration change tracking

- **Performance Monitoring**: Real-time monitoring and alerting system
  - Connection performance tracking
  - Memory usage monitoring
  - Query execution time tracking
  - Configurable alert thresholds
  - Performance dashboard integration

- **Enhanced Security Features**:
  - Encrypted credential storage with rotation support
  - Sensitive data masking in logs
  - Security event logging
  - Access control validation
  - Audit trail generation

- **Connection Management Improvements**:
  - Connection pooling for optimized performance
  - Automatic connection cleanup
  - Connection health monitoring
  - Configurable connection limits per tenant

- **Comprehensive Testing Suite**:
  - Unit tests for all core components
  - Integration tests for component interactions
  - Feature tests for complete workflows
  - Performance testing utilities
  - Security testing coverage

- **Enhanced Configuration System**:
  - Detailed configuration options
  - Environment variable support
  - Development/production configurations
  - Cache configuration options
  - Queue configuration for background jobs

- **Monitoring Commands**:
  - `tenancy:monitor-connections` - Monitor active connections
  - Support for different output formats (JSON, log)
  - Configurable monitoring intervals
  - Real-time performance metrics

### Enhanced
- **Installation Experience**:
  - Beautiful branded installer interface
  - Step-by-step installation wizard
  - Automatic dependency checking (Filament)
  - Smart configuration with user-friendly prompts
  - Connection retry mechanism (3 attempts)
  - Automatic cleanup on critical errors
  - **Automatic Plan Seeding**: PlanSeeder runs automatically after migrations to create default plans
  - **Seeder Publishing**: PlanSeeder is automatically published and can be customized

- **Tenant Creation**:
  - Interactive command with branded interface
  - Improved validation and error messages
  - Better user experience with clear prompts
  - Support for both domain and subdomain modes
  - **APP_DOMAIN Auto-Configuration**: Automatically detects and configures domain from `APP_URL`
  - **Smart Domain Detection**: Intelligently detects valid domains and suggests configuration
  - **Subdomain Support**: Subdomains now use `APP_DOMAIN` for full domain construction
  - **Environment Variable Management**: Automatic `.env` file updates for `APP_DOMAIN`
  - **Database-Driven Plan Selection**: Plans are now loaded from database, showing real plan names, prices, and billing cycles
  - **Automatic Subscription Creation**: When a tenant is created with a plan, a subscription is automatically created with active status
  - **Plan Integration**: Selected plan is stored as `plan_id` (foreign key) and `plan` (legacy string) for backward compatibility

- **Tenant Model Architecture**:
  - **Refactored Model Structure**: Separated business logic into internal `TenantCore` class
  - **Tenant Model**: Now contains only Eloquent-specific configuration (fillable, casts, dates, relationships)
  - **TenantCore Class**: Internal abstract class containing all business logic (methods, scopes, domain/URL logic)

- **Enhanced Security and Domain Validation**:
  - **Automatic 404 for Invalid Domains**: Middleware now automatically returns 404 when a domain/subdomain doesn't match any tenant
  - **Active Tenant Verification**: Only active tenants can be resolved (checks `is_active` and `expires_at`)
  - **Central Domain Protection**: `APP_DOMAIN` is automatically considered a central domain and will not resolve tenants
  - **Subdomain Resolution**: Enhanced subdomain resolution to check both `subdomain` field and `slug` field, with proper base domain validation
  - **Landlord Route Exception**: Landlord/admin routes can be accessed even when tenant is inactive (configurable paths)
  - **Middleware Integration**: `InitializeTenancy` middleware now handles tenant resolution and validation automatically
  - **Laravel 11 Middleware Registration**: Middlewares are automatically registered in `bootstrap/app.php` during installation for Laravel 11
  - **Improved Code Organization**: Clean separation between public API and internal implementation
  - **DomainResolver Integration**: Business logic now uses dedicated DomainResolver and TenantUrlGenerator classes
  - Added comprehensive fillable attributes for database connection fields
  - Plan management (`plan` and `plan_id` for foreign key relationship)
  - Enhanced data handling with proper casting
  - Relationships with Plan and Subscription models
  - **APP_DOMAIN Support**: `getFullDomain()` method now uses `APP_DOMAIN` environment variable for subdomain construction
  - **Improved Connection Handling**: Fixed `getConnectionName()` to properly use landlord connection via trait

- **Database Configuration**:
  - Dynamic connection template building from Laravel defaults
  - Support for MySQL, PostgreSQL, and SQLite detection
  - Automatic database name generation
  - Better connection error handling

- **Documentation**: Complete rewrite with comprehensive guides
  - Updated README with installation instructions
  - Interactive command examples
  - Database compatibility information
  - Troubleshooting section
  - Performance optimization guide

- **Error Handling**: Improved error handling and reporting
  - Custom exception classes
  - Detailed error messages
  - Proper error logging
  - Recovery mechanisms
  - Connection retry logic
  - Installation cleanup on failures

### Fixed
- **Filament 4 Compatibility**:
  - Fixed `Panel::databaseConnection()` method removal (Filament 4)
  - Updated `form()` method to use `Filament\Schemas\Schema` instead of `Filament\Forms\Form`
  - Updated `infolist()` method to use `Filament\Schemas\Schema` instead of `Filament\Infolists\Infolist`
  - Fixed `$navigationIcon` type hint to support `BackedEnum|string|null`

- **Logger Compatibility**:
  - Fixed `TenancyLogger::logConnection()` to accept nullable `Tenant` parameter
  - Improved error handling when switching to central connection

- **Command Improvements**:
  - Fixed `CreateTenantCommand` to handle optional arguments correctly
  - Improved interactive prompts validation
  - Fixed password handling in database configuration (null handling)
  - **Fixed Connection Error**: Resolved `Database connection [tenant_] not configured` error during tenant creation
  - **Tenant ID Validation**: Added proper validation to ensure tenant has ID before connection operations
  - **Database Name Generation**: Improved handling of database name generation for tenants without ID

- **Database Manager**:
  - Fixed SQLite compatibility checks
  - Improved PostgreSQL database creation syntax
  - Better error messages for connection failures
  - **Connection Name Generation**: Fixed issue where connection names were generated before tenant had ID
  - **Database Config Caching**: Improved caching logic to handle tenants without ID correctly

- **Namespace Issues**: Corrected all namespace imports
  - Fixed `CredentialManagerInterface` import in `CredentialManager`
  - Fixed `ConnectionException` import in `CredentialManager`
  - Fixed `CredentialManagerInterface` import in `ConnectionManager`

- **Test Configuration**: Simplified test setup
  - Removed problematic migration loading
  - Streamlined test case configuration
  - Fixed fillable attribute tests
  - Added basic connection tests

- **Model Configuration**: Updated Tenant model
  - Added missing fillable attributes
  - Proper attribute casting
  - Enhanced data handling
  - Added `plan_id` foreign key support
  - Added relationships with Plan and Subscription models

- **Code Architecture Improvements**:
  - **Refactored Tenant Model**: Separated into public Tenant model and internal TenantCore class
  - **Internal Class Structure**: Created `src/Models/Core/` directory for internal core classes
  - **Domain and URL Logic**: Extracted domain resolution and URL generation to dedicated support classes (`DomainResolver`, `TenantUrlGenerator`)
  - **Better Separation of Concerns**: Public model now focuses on Eloquent configuration, business logic in core class
  - **Improved Maintainability**: Business logic changes can be made in TenantCore without affecting public API
  - **Enhanced Extensibility**: Users can extend Tenant model without worrying about internal implementation details
  - **Cleaner Code Structure**: Tenant model is now minimal and focused, making it easier to understand and maintain

### Security
- **Credential Encryption**: All database credentials are now encrypted
- **Data Masking**: Sensitive information is masked in logs
- **Access Control**: Enhanced tenant isolation and access validation
- **Audit Trails**: Comprehensive logging of all security-related events

### Performance
- **Connection Pooling**: Implemented connection pooling for better performance
- **Caching**: Added comprehensive caching for tenant configurations and credentials
- **Memory Management**: Improved memory usage monitoring and optimization
- **Query Optimization**: Enhanced database query performance

### Developer Experience
- **Comprehensive Testing**: Full test suite with multiple test types
- **Better Documentation**: Detailed technical documentation and examples
- **Configuration Options**: Extensive configuration options for customization
- **Debug Tools**: Enhanced debugging and monitoring capabilities

## [1.0.0] - Initial Release

### Added
- Basic multi-tenancy support for Filament
- Tenant model with database isolation
- Basic connection management
- Filament panel integration
- Tenant resource management
- Basic middleware support

### Features
- Multi-database tenancy
- Tenant-aware routing
- Basic security features
- Simple configuration system
- Basic testing framework

---

## Migration Guide

### From 1.0.0 to Current Version

#### Configuration Changes
1. **Update Configuration File**: The configuration structure has been significantly enhanced
   ```bash
   php artisan vendor:publish --tag="filament-tenancy-config" --force
   ```

2. **Environment Variables**: Add new environment variables for enhanced features
   ```env
   # Logging
   TENANCY_LOGGING_ENABLED=true
   TENANCY_LOG_CHANNEL=tenancy
   TENANCY_LOG_LEVEL=info
   
   # Monitoring
   TENANCY_MONITORING_ENABLED=true
   TENANCY_PERFORMANCE_THRESHOLD=1000
   TENANCY_MEMORY_THRESHOLD=128
   
   # Security
   TENANCY_ENCRYPTION_KEY=your-encryption-key
   TENANCY_CREDENTIAL_ROTATION_DAYS=90
   ```

#### Database Changes
1. **Tenant Model Updates**: The Tenant model now includes additional fillable attributes
   ```php
   // New fillable attributes added:
   'database_name', 'database_host', 'database_port', 
   'database_username', 'database_password', 'plan'
   ```

2. **Migration Updates**: Run migrations to update the tenant table structure
   ```bash
   php artisan migrate
   ```

#### Code Changes
1. **Namespace Updates**: Update any custom implementations to use correct namespaces
   ```php
   // Old
   use AngelitoSystems\FilamentTenancy\Contracts\CredentialManagerInterface;
   
   // New
   use AngelitoSystems\FilamentTenancy\Support\Contracts\CredentialManagerInterface;
   ```

2. **New Features**: Take advantage of new logging and monitoring features
   ```php
   // Logging
   $logger = app(TenancyLogger::class);
   $logger->logTenantConnection($tenant, 'switched');
   
   // Monitoring
   php artisan tenancy:monitor-connections
   ```

#### Testing Updates
1. **Test Configuration**: Update test configurations for new features
2. **New Test Types**: Consider adding integration and feature tests
3. **Performance Testing**: Utilize new performance testing utilities

### Breaking Changes
- **Configuration Structure**: The configuration file structure has changed significantly
- **Namespace Changes**: Some classes have moved to different namespaces
- **Interface Updates**: Some interfaces have been enhanced with new methods

### Deprecations
- **Old Configuration Keys**: Some old configuration keys are deprecated but still supported
- **Legacy Methods**: Some legacy methods are deprecated in favor of new implementations

---

## Support

For questions, issues, or contributions:

- **Issues**: [GitHub Issues](https://github.com/angelitosystems/filament-tenancy/issues)
- **Discussions**: [GitHub Discussions](https://github.com/angelitosystems/filament-tenancy/discussions)
- **Documentation**: [Technical Documentation](docs/TECHNICAL.md)

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this project.