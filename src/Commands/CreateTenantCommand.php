<?php

namespace AngelitoSystems\FilamentTenancy\Commands;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Models\Plan;
use AngelitoSystems\FilamentTenancy\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:create 
                            {name? : The name of the tenant}
                            {--slug= : The slug for the tenant (auto-generated if not provided)}
                            {--domain= : The domain for the tenant}
                            {--subdomain= : The subdomain for the tenant}
                            {--database= : The database name for the tenant (auto-generated if not provided)}
                            {--plan= : The plan for the tenant}
                            {--active : Mark the tenant as active (default: true)}
                            {--expires= : Expiration date for the tenant (YYYY-MM-DD format)}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new tenant interactively';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayBranding();

        // Por defecto es interactivo si no se proporcionan argumentos u opciones requeridas
        $name = $this->argument('name');
        $domain = $this->option('domain');
        $subdomain = $this->option('subdomain');
        
        // Determinar si debe ser interactivo:
        // - Por defecto es interactivo si no se proporcionan todos los datos requeridos
        // - NO es interactivo si se usa --no-interaction O si se proporcionan todos los datos requeridos
        $hasRequiredData = $name && ($domain || $subdomain);
        $isInteractive = !$this->option('no-interaction') && !$hasRequiredData;

        if ($isInteractive) {
            $this->info('Creando un nuevo tenant...');
            $this->newLine();
        }

        // Verificar y configurar APP_DOMAIN si es necesario
        $this->checkAndConfigureAppDomain($isInteractive);

        // Obtener datos interactivamente o desde argumentos/opciones
        $name = $name ?: $this->ask('Nombre del tenant', null);
        if (!$name) {
            $this->error('El nombre del tenant es requerido.');
            return self::FAILURE;
        }

        $slug = $this->option('slug') ?: ($isInteractive ? $this->ask('Slug', Str::slug($name)) : Str::slug($name));
        
        // Obtener dominio o subdominio (al menos uno es requerido)
        $domain = $this->option('domain');
        $subdomain = $this->option('subdomain');
        
        if ($isInteractive && !$domain && !$subdomain) {
            $domainOrSubdomain = $this->choice(
                '¿Qué tipo de identificación usarás?',
                [
                    'domain' => 'Dominio completo (ej: example.com)',
                    'subdomain' => 'Subdominio (ej: tenant)'
                ],
                'subdomain'
            );
            
            if ($domainOrSubdomain === 'domain') {
                $domain = $this->ask('Dominio completo (ej: example.com)', null);
                while (!$domain) {
                    $this->warn('El dominio es requerido.');
                    $domain = $this->ask('Dominio completo (ej: example.com)', null);
                }
            } else {
                $subdomain = $this->ask('Subdominio (ej: tenant)', null);
                while (!$subdomain) {
                    $this->warn('El subdominio es requerido.');
                    $subdomain = $this->ask('Subdominio (ej: tenant)', null);
                }
            }
        } elseif (!$domain && !$subdomain) {
            $this->error('Se debe proporcionar --domain o --subdomain.');
            return self::FAILURE;
        }
        
        $database = $this->option('database') ?: ($isInteractive ? $this->ask('Nombre de la base de datos (dejar vacío para auto-generar)', null) : null);
        
        // Cargar planes desde la base de datos
        $planSlug = $this->option('plan');
        $planModel = null;
        
        if ($isInteractive && !$planSlug) {
            $plans = Plan::active()->orderBy('sort_order')->get();
            
            if ($plans->isEmpty()) {
                $this->warn('⚠ No hay planes disponibles. Ejecuta el seeder: <fg=yellow>php artisan db:seed --class=Database\\Seeders\\PlanSeeder</fg=yellow>');
                $planSlug = null;
            } else {
                $planChoices = [];
                foreach ($plans as $plan) {
                    $planChoices[$plan->slug] = "{$plan->name} ({$plan->getFormattedPriceAttribute()}/{$plan->billing_cycle})";
                }
                
                $planSlug = $this->choice('Plan', $planChoices, null);
                $planModel = $plans->firstWhere('slug', $planSlug);
            }
        } elseif ($planSlug) {
            $planModel = Plan::where('slug', $planSlug)->first();
            if (!$planModel) {
                $this->warn("⚠ Plan '{$planSlug}' no encontrado. El tenant se creará sin plan.");
            }
        }
        
        $isActive = $this->option('active') !== false;
        if ($isInteractive && !$this->option('active')) {
            $isActive = $this->confirm('¿Activar el tenant inmediatamente?', true);
        }
        $expires = $this->option('expires') ?: ($isInteractive ? $this->ask('Fecha de expiración (YYYY-MM-DD, dejar vacío para nunca)', null) : null);

        // Check if tenant already exists
        $existingTenant = null;
        if ($domain) {
            $existingTenant = Tenancy::findTenantByDomain($domain);
        } elseif ($subdomain) {
            $existingTenant = Tenancy::runForCentral(function () use ($subdomain) {
                return \AngelitoSystems\FilamentTenancy\Models\Tenant::where('subdomain', $subdomain)->first();
            });
        }

        if ($existingTenant) {
            $this->error('A tenant with this domain/subdomain already exists.');
            return self::FAILURE;
        }

        // Prepare tenant data
        $tenantData = [
            'name' => $name,
            'slug' => $slug,
            'is_active' => $isActive,
        ];

        if ($domain) {
            $tenantData['domain'] = $domain;
        }

        if ($subdomain) {
            $tenantData['subdomain'] = $subdomain;
        }

        if ($database) {
            $tenantData['database_name'] = $database;
        }

        // Guardar plan_id si se seleccionó un plan
        if ($planModel) {
            $tenantData['plan_id'] = $planModel->id;
            $tenantData['plan'] = $planModel->slug; // Mantener compatibilidad con legacy
        } elseif ($planSlug) {
            // Si se proporcionó un slug pero no se encontró el plan, guardar como string legacy
            $tenantData['plan'] = $planSlug;
        }

        if ($expires) {
            try {
                $tenantData['expires_at'] = \Carbon\Carbon::createFromFormat('Y-m-d', $expires);
            } catch (\Exception $e) {
                $this->error('Invalid expiration date format. Use YYYY-MM-DD format.');
                return self::FAILURE;
            }
        }

        try {
            // Verificar compatibilidad de base de datos antes de crear
            $driver = env('DB_CONNECTION', 'mysql');
            if ($driver === 'sqlite') {
                $this->error('⚠ SQLite no es compatible con multi-tenancy multi-database.');
                $this->newLine();
                $this->line('Para usar multi-tenancy con múltiples bases de datos, necesitas MySQL o PostgreSQL.');
                $this->line('Ejecuta <fg=yellow>php artisan filament-tenancy:install</fg=yellow> para configurar una conexión compatible.');
                $this->newLine();
                return self::FAILURE;
            }

            if (!$isInteractive) {
                $this->info('Creando tenant...');
            } else {
                $this->newLine();
                $this->info('🔄 Creando tenant...');
            }
            
            $tenant = Tenancy::createTenant($tenantData);
            
            // Crear suscripción automáticamente si se seleccionó un plan
            $subscription = null;
            if ($planModel) {
                try {
                    $subscription = Subscription::create([
                        'tenant_id' => $tenant->id,
                        'plan_id' => $planModel->id,
                        'status' => Subscription::STATUS_ACTIVE,
                        'starts_at' => now(),
                    ]);
                    $this->line("  ✓ Suscripción creada para el plan: <fg=green>{$planModel->name}</fg=green>");
                } catch (\Exception $e) {
                    $this->warn("  ⚠ No se pudo crear la suscripción: {$e->getMessage()}");
                }
            }
            
            $this->newLine();
            $this->info("✓ Tenant '{$tenant->name}' creado exitosamente!");
            $this->newLine();
            
            $planDisplay = $planModel ? "{$planModel->name} ({$planModel->getFormattedPriceAttribute()})" : ($tenant->plan ?: 'N/A');
            
            $this->table(
                ['Propiedad', 'Valor'],
                [
                    ['ID', $tenant->id],
                    ['Nombre', $tenant->name],
                    ['Slug', $tenant->slug],
                    ['Dominio', $tenant->domain ?: 'N/A'],
                    ['Subdominio', $tenant->subdomain ?: 'N/A'],
                    ['Base de Datos', $tenant->database_name],
                    ['Activo', $tenant->is_active ? 'Sí' : 'No'],
                    ['Plan', $planDisplay],
                    ['Suscripción', $subscription ? 'Activa' : 'N/A'],
                    ['Expira', $tenant->expires_at ? $tenant->expires_at->format('Y-m-d') : 'Nunca'],
                    ['URL', $tenant->getUrl()],
                ]
            );

            $this->newLine();
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('✗ Error al crear el tenant: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Muestra el branding inicial del paquete.
     */
    protected function displayBranding(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════════╗');
        $this->line('║                                                               ║');
        $this->line('║           <fg=cyan>Filament Tenancy</fg=cyan> - Multi-Tenancy Package        ║');
        $this->line('║                  <fg=yellow>Angelito Systems</fg=yellow>                      ║');
        $this->line('║                                                               ║');
        $this->line('╚═══════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    /**
     * Verifica y configura APP_DOMAIN si es necesario.
     */
    protected function checkAndConfigureAppDomain(bool $isInteractive): void
    {
        $appUrl = env('APP_URL');
        $appDomain = env('APP_DOMAIN');

        // Si ya existe APP_DOMAIN, no hacer nada
        if ($appDomain) {
            return;
        }

        // Si no hay APP_URL, no hacer nada
        if (!$appUrl) {
            return;
        }

        // Parsear APP_URL
        $parsedUrl = parse_url($appUrl);
        
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return;
        }

        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? null;

        // Verificar si es localhost o tiene puerto
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']);
        $hasPort = $port !== null;

        // Si es localhost o tiene puerto, preguntar al usuario
        if ($isLocalhost || $hasPort) {
            if ($isInteractive) {
                $this->newLine();
                $this->info('🔍 Detectado APP_URL: ' . $appUrl);
                
                if ($isLocalhost) {
                    $this->warn('⚠ APP_URL apunta a localhost.');
                } else {
                    $this->warn('⚠ APP_URL contiene un puerto (' . $port . ').');
                }
                
                $this->line('Para usar subdominios, necesitas configurar APP_DOMAIN.');
                $this->newLine();
                
                $shouldConfigure = $this->confirm('¿Deseas configurar APP_DOMAIN ahora?', false);
                
                if ($shouldConfigure) {
                    $suggestedDomain = $this->ask('Ingresa el dominio base (ej: hola.test, example.com)', null);
                    
                    if ($suggestedDomain) {
                        $this->updateEnvFile('APP_DOMAIN', $suggestedDomain);
                        $this->info('✓ APP_DOMAIN configurado: ' . $suggestedDomain);
                        $this->newLine();
                    }
                }
            }
            return;
        }

        // Si es un dominio válido (como hola.test), preguntar si desea usarlo
        if ($isInteractive && preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $host)) {
            $this->newLine();
            $this->info('🔍 Detectado dominio en APP_URL: ' . $host);
            $this->newLine();
            
            $shouldUse = $this->confirm('¿Deseas usar este dominio como APP_DOMAIN?', true);
            
            if ($shouldUse) {
                $this->updateEnvFile('APP_DOMAIN', $host);
                $this->info('✓ APP_DOMAIN configurado: ' . $host);
                $this->newLine();
            }
        }
    }

    /**
     * Actualiza una variable en el archivo .env.
     */
    protected function updateEnvFile(string $key, string $value): void
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);
        
        // Si la clave ya existe, reemplazarla
        if (preg_match("/^{$key}=.*$/m", $envContent)) {
            $envContent = preg_replace("/^{$key}=.*$/m", "{$key}={$value}", $envContent);
        } else {
            // Si no existe, agregarla al final
            $envContent .= "\n{$key}={$value}\n";
        }

        file_put_contents($envPath, $envContent);
    }
}