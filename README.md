# Filament Tenancy

A comprehensive multi-tenancy package for Filament with support for multiple databases, central management, advanced logging, and performance monitoring.

## Features

- **🚀 Easy Installation** - Interactive installer with automatic setup
- **🗄️ Multi-database tenancy** - Complete isolation between tenants
- **🎛️ Central tenant management** - Unified control panel for all tenants
- **⚡ Automatic database creation and migration** - Seamless tenant provisioning
- **🔄 Tenant-aware middleware** - Automatic context switching
- **🎨 Filament integration** - Native Filament admin panel support
- **🔐 Secure credential management** - Encrypted credential storage with rotation
- **📊 Advanced logging system** - Comprehensive audit trails and monitoring
- **📈 Performance monitoring** - Real-time metrics and connection monitoring
- **🔌 Connection pooling** - Optimized database connection management
- **💳 Plans & Subscriptions** - Built-in plan and subscription management
- **🛡️ Database compatibility check** - Automatic validation of database requirements
- **🧹 Smart error handling** - Automatic retry and cleanup on connection errors
- **🌐 APP_DOMAIN Auto-Detection** - Automatic domain detection from APP_URL for subdomain tenancy
- **🔧 Enhanced Tenant Creation** - Interactive wizard with automatic domain configuration
- **🎨 Custom 404 Page** - Beautiful personalized 404 page for tenant not found errors with Livewire component support
- **⚖️ License-Based** - Future licensing model for commercial use (currently public access)

## Installation

### Quick Install

The easiest way to install Filament Tenancy is using the interactive installer:

```bash
composer require angelitosystems/filament-tenancy
php artisan filament-tenancy:install
```

The installer will:
- ✅ Check if Filament is installed and install it if needed
- ✅ Verify database compatibility (MySQL/PostgreSQL required)
- ✅ Configure database connection interactively if needed
- ✅ Publish configuration files
- ✅ Run migrations automatically
- ✅ **Create default plans automatically** (Basic, Premium, Enterprise)
- ✅ Register the ServiceProvider automatically
- ✅ **Register middlewares automatically** in `bootstrap/app.php` (Laravel 11) or via ServiceProvider (Laravel 10)
- ✅ **Configure custom 404 page** - Ask if you want to publish components and views for tenant not found errors

### Manual Installation

If you prefer manual installation:

```bash
# Install the package
composer require angelitosystems/filament-tenancy

# Publish the configuration file
php artisan vendor:publish --tag="filament-tenancy-config"

# Publish the plan seeder (optional, can be customized)
php artisan vendor:publish --tag="filament-tenancy-seeders"

# Publish custom 404 page components and views (optional)
php artisan vendor:publish --tag="filament-tenancy-views"
php artisan vendor:publish --tag="filament-tenancy-components"

# Run the migrations
php artisan migrate

# Seed default plans (or they will be seeded automatically during installation)
php artisan db:seed --class=Database\Seeders\PlanSeeder
```

### Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 10.x or 11.x
- **Filament**: ^4.0
- **Database**: MySQL 5.7+ or PostgreSQL 10+ (SQLite is not supported for multi-database tenancy)

## Configuration

The package configuration is located at `config/filament-tenancy.php`. Key configuration options include:

### Tenant Resolution

```php
// How tenants are resolved from requests
'resolver' => env('TENANCY_RESOLVER', 'domain'), // 'domain', 'subdomain', 'path'

// Central domains that won't be resolved as tenants
'central_domains' => [
    'app.dental.test',
    'localhost',
    env('APP_DOMAIN', 'localhost'),
],
```

**Note**: The package now supports `APP_DOMAIN` environment variable for subdomain-based tenancy. When creating tenants with subdomains, the package will automatically detect and suggest configuring `APP_DOMAIN` from your `APP_URL` if it's not already set.

### Database Configuration

```php
'database' => [
    'default_connection' => env('DB_CONNECTION', 'mysql'),
    'tenants_connection_template' => [
        'driver' => env('TENANT_DB_DRIVER', 'mysql'),
        'host' => env('TENANT_DB_HOST', '127.0.0.1'),
        'port' => env('TENANT_DB_PORT', '3306'),
        'username' => env('TENANT_DB_USERNAME', 'root'),
        'password' => env('TENANT_DB_PASSWORD', ''),
        // ... other database options
    ],
    'auto_create_tenant_database' => env('TENANCY_AUTO_CREATE_DB', true),
    'auto_delete_tenant_database' => env('TENANCY_AUTO_DELETE_DB', false),
],
```

### Environment Variables

The package uses the following environment variables:

```env
# Base domain for subdomain-based tenancy (auto-detected from APP_URL)
APP_DOMAIN=hola.test

# Tenant resolution strategy
TENANCY_RESOLVER=domain

# Database configuration (optional, uses DB_* by default)
TENANT_DB_DRIVER=mysql
TENANT_DB_HOST=127.0.0.1
TENANT_DB_PORT=3306
TENANT_DB_USERNAME=root
TENANT_DB_PASSWORD=

# Tenancy settings
TENANCY_AUTO_CREATE_DB=true
TENANCY_AUTO_DELETE_DB=false
```

**APP_DOMAIN**: This variable is automatically detected and configured when creating tenants. If your `APP_URL` contains a valid domain (e.g., `http://hola.test`), the package will ask if you want to use it as `APP_DOMAIN`. This is essential for subdomain-based tenancy.

**Central Domains**: `APP_DOMAIN` is automatically considered a central domain and will not resolve tenants. Additional domains can be configured in `central_domains` config array. Central domains typically host the admin panel for managing tenants. The middleware automatically allows access to these domains without tenant resolution.

**Security**: The middleware automatically:
- Returns 404 for domains/subdomains that don't match any tenant
- Only resolves active tenants (`is_active = true` and not expired)
- Protects central domains from tenant resolution
- Validates tenant status before allowing access

### Filament Integration

```php
'filament' => [
    'auto_register_plugins' => true,
    'landlord_panel_id' => 'admin',
    'tenant_panel_id' => 'tenant',
    'tenant_panel_path' => '/admin',
],
```

## Usage

### Creating Tenants

#### Using Artisan Commands (Interactive)

The easiest way to create tenants is using the interactive command:

```bash
# Interactive tenant creation
php artisan tenancy:create
```

The command will guide you through:
- **APP_DOMAIN Detection**: Automatically detects and configures `APP_DOMAIN` from `APP_URL` if needed
- Tenant name and slug
- Domain or subdomain configuration (subdomains use `APP_DOMAIN` automatically)
- Database name (auto-generated if not provided)
- **Plan selection**: Shows plans from database with prices and billing cycles (Basic, Premium, Enterprise)
- Active status and expiration date
- **Automatic subscription creation**: Creates an active subscription when a plan is selected

**APP_DOMAIN Auto-Configuration**:
- **Valid Domain Detection**: If `APP_URL` contains a valid domain (e.g., `http://hola.test`), the command will detect it and ask if you want to use it as `APP_DOMAIN`
- **Localhost/Port Detection**: If `APP_URL` is localhost or has a port (e.g., `http://localhost:8000`), you'll be prompted to configure `APP_DOMAIN` manually
- **Automatic .env Updates**: The `APP_DOMAIN` variable is automatically added or updated in your `.env` file
- **Subdomain Support**: When using subdomains, the full domain is automatically constructed using `APP_DOMAIN` (e.g., `tenant.APP_DOMAIN`)

#### Using Artisan Commands (Non-Interactive)

```bash
# Create a new tenant with all options (plan slug must exist in database)
php artisan tenancy:create "Acme Corp" \
    --subdomain="acme" \
    --database="acme_db" \
    --plan="premium" \
    --active \
    --expires="2025-12-31"

# Create with domain
php artisan tenancy:create "Acme Corp" --domain="acme.com"

# Create with subdomain
php artisan tenancy:create "Acme Corp" --subdomain="acme"
```

**Note**: When using `--plan`, the plan slug must exist in the `tenancy_plans` table. If a plan is provided, a subscription will be automatically created for the tenant.

#### Programmatically

```php
use AngelitoSystems\FilamentTenancy\Facades\Tenancy;

// Create a new tenant
$tenant = Tenancy::createTenant([
    'name' => 'Acme Corporation',
    'slug' => 'acme-corp',
    'domain' => 'acme.com',
    'is_active' => true,
]);

// Switch to tenant context
Tenancy::switchToTenant($tenant);

// Run code in tenant context
Tenancy::runForTenant($tenant, function () {
    // This code runs in the tenant's database context
    User::create(['name' => 'John Doe', 'email' => 'john@acme.com']);
});

// Switch back to central context
Tenancy::switchToCentral();
```

### Working with Models

#### Tenant Models

For models that belong to tenants, use the `BelongsToTenant` trait:

```php
use AngelitoSystems\FilamentTenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use BelongsToTenant;
    
    // Your model code...
}
```

#### Central/Landlord Models

For models that should always use the central database, use the `UsesLandlordConnection` trait:

```php
use AngelitoSystems\FilamentTenancy\Concerns\UsesLandlordConnection;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use UsesLandlordConnection;
    
    // Your model code...
}
```

### Filament Panel Configuration

#### Landlord Panel

Create a landlord panel for managing tenants:

```php
// app/Providers/Filament/AdminPanelProvider.php
use AngelitoSystems\FilamentTenancy\FilamentPlugins\LandlordPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('/admin')
        ->plugin(LandlordPlugin::make())
        // ... other panel configuration
}
```

#### Tenant Panel

Create a tenant panel:

```php
// app/Providers/Filament/TenantPanelProvider.php
use AngelitoSystems\FilamentTenancy\FilamentPlugins\TenantPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('tenant')
        ->path('/admin')
        ->plugin(TenantPlugin::make())
        // ... other panel configuration
}
```

### Middleware

The package provides several middleware for tenant management:

- **`InitializeTenancy`**: 
  - Automatically resolves tenant from domain/subdomain
  - Returns custom 404 page if tenant is not found (if published, otherwise standard 404)
  - Verifies tenant is active before allowing access
  - Allows access to landlord/admin routes even with inactive tenants (configurable)
  - Registered globally by default (can be disabled in config)
  
- **`EnsureTenantAccess`**: 
  - Ensures a tenant is present and active
  - Returns 404 if no tenant is found
  - Returns 403 if tenant is inactive or expired
- `PreventAccessFromCentralDomains`: Prevents tenant access from central domains

### Custom 404 Page

The package includes a beautiful, personalized 404 page for tenant not found errors. During installation, you'll be asked if you want to publish the components and views for customization.

#### Publishing Components

```bash
# Publish views (Blade templates)
php artisan vendor:publish --tag="filament-tenancy-views"

# Publish Livewire component (optional, requires Livewire)
php artisan vendor:publish --tag="filament-tenancy-components"
```

#### Published Files

If you choose to publish during installation:
- **Views**: `resources/views/vendor/filament-tenancy/errors/tenant-not-found.blade.php`
- **Livewire Component**: `app/Livewire/TenantNotFound.php` (if Livewire is available)

#### Customization

Once published, you can fully customize:
- **Blade View**: Edit `resources/views/vendor/filament-tenancy/errors/tenant-not-found.blade.php` to change the design, colors, layout, or content
- **Livewire Component**: Edit `app/Livewire/TenantNotFound.php` to add dynamic functionality or interactivity

#### Automatic Registration

The installer automatically registers the custom 404 page in `bootstrap/app.php` (Laravel 11) for handling tenant not found errors. The page will:
- Display a beautiful error message
- Show request details (domain, resolver, APP_DOMAIN)
- Provide a link back to the homepage
- Work without Livewire if not published

#### Without Publishing

If you choose not to publish, the package will use its internal views and components. The 404 page will still work, but you won't be able to customize it.

### Database Migrations

#### Running Tenant Migrations

```bash
# Run migrations for all tenants
php artisan tenant:migrate

# Run migrations for a specific tenant
php artisan tenant:migrate --tenant=1

# Run fresh migrations with seeding
php artisan tenant:migrate --fresh --seed
```

#### Creating Tenant-Specific Migrations

Place tenant-specific migrations in `database/migrations/tenant/`:

```bash
php artisan make:migration create_tenant_users_table --path=database/migrations/tenant
```

### Events

The package dispatches several events:

- `TenantCreated`: When a new tenant is created
- `TenantDeleted`: When a tenant is deleted
- `TenantSwitched`: When switching between tenants

```php
use AngelitoSystems\FilamentTenancy\Events\TenantCreated;

Event::listen(TenantCreated::class, function (TenantCreated $event) {
    // Handle tenant creation
    $tenant = $event->tenant;
});
```

## Advanced Usage

### Custom Tenant Resolution

You can extend the tenant resolver for custom logic:

```php
use AngelitoSystems\FilamentTenancy\Support\TenantResolver;

class CustomTenantResolver extends TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        // Your custom resolution logic
        return parent::resolve($request);
    }
}

// Register in a service provider
$this->app->bind(TenantResolver::class, CustomTenantResolver::class);
```

### Custom Database Configuration

Override database configuration per tenant:

```php
$tenant = Tenant::find(1);
$tenant->update([
    'database_host' => 'custom-host.com',
    'database_name' => 'custom_database',
    'database_username' => 'custom_user',
    'database_password' => 'custom_password',
]);
```

### Tenant Data Storage

Store additional tenant data using the JSON `data` column:

```php
$tenant->data = [
    'settings' => [
        'theme' => 'dark',
        'timezone' => 'UTC',
    ],
    'features' => ['feature1', 'feature2'],
];
$tenant->save();

// Access data
$theme = $tenant->data['settings']['theme'] ?? 'light';
```

### APP_DOMAIN Configuration

The `APP_DOMAIN` environment variable is used for subdomain-based tenancy. It's automatically detected and configured during tenant creation:

**Automatic Detection**:
- When running `php artisan tenancy:create`, the package checks your `APP_URL`
- If `APP_URL` contains a valid domain (e.g., `http://hola.test`), it suggests using it as `APP_DOMAIN`
- If `APP_URL` is localhost or has a port, you'll be prompted to configure `APP_DOMAIN` manually

**Manual Configuration**:
```env
APP_DOMAIN=hola.test
```

**Usage**:
- When creating tenants with subdomains, the full domain is automatically constructed: `{subdomain}.{APP_DOMAIN}`
- For example, if `APP_DOMAIN=hola.test` and subdomain is `acme`, the full domain becomes `acme.hola.test`
- The `Tenant::getFullDomain()` method uses `APP_DOMAIN` when available, falling back to `central_domains` configuration

**Example**:
```php
// With APP_DOMAIN=hola.test configured
$tenant = Tenant::create([
    'name' => 'Acme Corp',
    'subdomain' => 'acme',
]);

echo $tenant->getFullDomain(); // Output: acme.hola.test
echo $tenant->getUrl(); // Output: http://acme.hola.test
```

## Testing

Run the package tests:

```bash
composer test
```

## Security

This package includes several security features:

- **Cross-tenant isolation**: Prevents data leakage between tenants
- **Domain validation**: Ensures tenants can only be accessed from their domains
- **Database separation**: Each tenant has its own database
- **Middleware protection**: Automatic tenant context validation
- **Automatic 404 for invalid domains**: Domains/subdomains that don't match any tenant automatically return a beautiful custom 404 page
- **Customizable 404 page**: Personalized error page with request details and optional Livewire component support
- **Active tenant verification**: Only active tenants can be accessed (checks `is_active` and `expires_at`)
- **Central domain protection**: Central domains are protected from tenant resolution

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

This package is currently available for public use, but will transition to a license-based distribution model in the future.

### ⚖️ License Terms & Usage Rights

**Current Status**: Public Access (Temporary)
- ✅ **You CAN**: Use this software in your projects (personal, commercial, educational)
- ✅ **You CAN**: Install and use the package via Composer
- ✅ **You CAN**: Customize configurations and extend functionality
- ❌ **You CANNOT**: Create replicas, forks, or copies of this package
- ❌ **You CANNOT**: Redistribute this package as your own
- ❌ **You CANNOT**: Remove or modify license headers
- ❌ **You CANNOT**: Use this package to create competing multi-tenancy solutions

### 🔮 Future Licensing Model

**Important**: This package will transition to a **paid license model** in the future. Users who adopt the package now will receive preferential treatment when licensing becomes available.

- License-based distribution (coming soon)
- Commercial use will require a valid license
- Enterprise features will be license-gated
- Early adopters will have migration paths to licensed versions

### ⚠️ What Happens If You Violate the License?

**Legal Consequences**:
- You may be subject to legal action for copyright infringement
- Distribution of unauthorized copies may result in cease and desist orders
- Commercial use without proper licensing will be pursued legally
- Creation of replicas or competing solutions will be treated as intellectual property theft

**Technical Consequences**:
- Package updates may include license validation
- Unauthorized usage may be detected and blocked
- Support will not be provided to unlicensed users
- Access to future versions may be restricted

### 📄 License Compliance

To ensure compliance:
- ✅ Use the package via official Composer repository only
- ✅ Do not copy, fork, or replicate the source code
- ✅ Respect intellectual property rights
- ✅ Contact us for licensing inquiries: angelitosystems@gmail.com

**Current License**: MIT License (subject to above restrictions)

For complete license terms, please read the [LICENSE](LICENSE) file.

## Credits

- [Angelito Systems](https://github.com/angelitosystems)
- [All Contributors](../../contributors)

## Support

For support, please open an issue on GitHub or contact us at angelitosystems@gmail.com.