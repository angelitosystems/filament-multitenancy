<?php

namespace AngelitoSystems\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filament-tenancy:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Instala y configura el paquete Filament Tenancy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayBranding();

        $this->info('Instalando Filament Tenancy...');
        $this->newLine();

        // Verificar e instalar Filament si es necesario
        $this->checkAndInstallFilament();

        // Verificar y configurar base de datos
        $this->checkAndConfigureDatabase();

        // Publicar configuración
        $this->publishConfiguration();

        // Ejecutar migraciones
        $this->runMigrations();

        // Registrar ServiceProvider
        $this->registerServiceProvider();

        // Registrar middlewares en bootstrap/app.php (Laravel 11)
        $this->registerMiddlewares();

        // Publicar componentes y vistas de 404
        $this->publish404Components();

        // Mensaje final
        $this->displaySuccessMessage();

        return self::SUCCESS;
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
     * Verifica e instala Filament si es necesario.
     */
    protected function checkAndInstallFilament(): void
    {
        $this->info('🔍 Verificando instalación de Filament...');

        // Verificar si Filament está instalado
        if (class_exists(\Filament\Facades\Filament::class)) {
            $this->line('  ✓ Filament ya está instalado');
            $this->newLine();
            return;
        }

        // Verificar si el paquete está en composer.json
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composerContent = json_decode(File::get($composerPath), true);
            $require = $composerContent['require'] ?? [];
            $requireDev = $composerContent['require-dev'] ?? [];
            $allRequire = array_merge($require, $requireDev);

            if (isset($allRequire['filament/filament'])) {
                $this->line('  ℹ Filament está en composer.json pero no está cargado correctamente');
                $this->warn('  ⚠ Ejecuta <fg=yellow>composer install</fg=yellow> y <fg=yellow>php artisan filament:install --panels</fg=yellow>');
                $this->newLine();
                return;
            }
        }

        $this->warn('  ⚠ Filament no está instalado.');
        $this->newLine();
        $this->line('  Filament Tenancy requiere Filament para funcionar.');
        $this->newLine();

        if (!$this->confirm('¿Deseas instalar Filament ahora?', true)) {
            $this->warn('  ⚠ Deberás instalar Filament manualmente más tarde.');
            $this->line('  Ejecuta: <fg=yellow>composer require filament/filament:"^4.0"</fg=yellow>');
            $this->line('  Luego: <fg=yellow>php artisan filament:install --panels</fg=yellow>');
            $this->newLine();
            return;
        }

        $this->installFilament();
    }

    /**
     * Instala Filament.
     */
    protected function installFilament(): void
    {
        $this->newLine();
        $this->info('📦 Instalando Filament...');

        // Instalar paquete via composer
        $this->line('  Ejecutando composer require filament/filament:"^4.0"...');
        
        $composerCommand = 'composer require filament/filament:"^4.0"';
        $output = [];
        $returnVar = 0;
        
        exec($composerCommand . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $this->error('  ✗ Error al instalar Filament via Composer');
            $this->warn('  ⚠ Ejecuta manualmente: <fg=yellow>composer require filament/filament:"^4.0"</fg=yellow>');
            $this->newLine();
            if (!empty($output)) {
                $this->line('  Salida del comando:');
                foreach ($output as $line) {
                    $this->line('    ' . $line);
                }
            }
            $this->newLine();
            return;
        }

        $this->line('  ✓ Filament instalado via Composer');

        // Instalar Filament panels
        $this->newLine();
        $this->line('  Ejecutando filament:install --panels...');
        
        try {
            $this->call('filament:install', [
                '--panels' => true,
            ]);
            $this->line('  ✓ Filament configurado');
        } catch (\Exception $e) {
            $this->warn('  ⚠ No se pudo ejecutar filament:install automáticamente');
            $this->line('  Ejecuta manualmente: <fg=yellow>php artisan filament:install --panels</fg=yellow>');
        }

        $this->newLine();
    }

    /**
     * Verifica y configura la base de datos.
     */
    protected function checkAndConfigureDatabase(): void
    {
        $this->info('🔍 Verificando configuración de base de datos...');

        $currentConnection = env('DB_CONNECTION', 'mysql');
        $compatibleConnections = ['mysql', 'pgsql'];

        if (!in_array($currentConnection, $compatibleConnections)) {
            $this->warn("  ⚠ La conexión actual (<fg=yellow>{$currentConnection}</fg=yellow>) no es compatible con multi-tenancy multi-database.");
            $this->newLine();
            $this->line('  Para multi-tenancy con múltiples bases de datos, necesitas MySQL o PostgreSQL.');
            $this->line('  SQLite solo permite una base de datos por archivo.');
            $this->newLine();

            if (!$this->confirm('¿Deseas configurar una conexión compatible ahora?', true)) {
                $this->warn('  ⚠ Deberás configurar manualmente una conexión compatible más tarde.');
                $this->newLine();
                return;
            }

            $this->configureDatabaseConnection();
        } else {
            $this->line("  ✓ Conexión compatible detectada: <fg=green>{$currentConnection}</fg=green>");
            $this->newLine();
        }
    }

    /**
     * Configura la conexión de base de datos.
     */
    protected function configureDatabaseConnection(): void
    {
        $this->newLine();
        $this->info('📊 Configurando conexión de base de datos...');

        $driver = $this->choice(
            '¿Qué tipo de base de datos quieres usar?',
            ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL'],
            'mysql'
        );

        $this->newLine();
        $this->line('Ingresa las credenciales de tu base de datos:');
        $this->newLine();

        $host = $this->ask('Host', '127.0.0.1');
        $port = $this->ask('Puerto', $driver === 'pgsql' ? '5432' : '3306');
        $database = $this->ask('Nombre de la base de datos', 'laravel');
        $username = $this->ask('Usuario', 'root');
        $password = $this->secret('Contraseña') ?? '';

        $this->newLine();
        $this->info('🔄 Actualizando archivo .env...');

        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            $this->error('  ✗ No se encontró el archivo .env');
            return;
        }

        $envContent = File::get($envPath);

        // Actualizar valores
        $envContent = preg_replace('/^DB_CONNECTION=.*/m', "DB_CONNECTION={$driver}", $envContent);
        $envContent = preg_replace('/^DB_HOST=.*/m', "DB_HOST={$host}", $envContent);
        $envContent = preg_replace('/^DB_PORT=.*/m', "DB_PORT={$port}", $envContent);
        $envContent = preg_replace('/^DB_DATABASE=.*/m', "DB_DATABASE={$database}", $envContent);
        $envContent = preg_replace('/^DB_USERNAME=.*/m', "DB_USERNAME={$username}", $envContent);
        $envContent = preg_replace('/^DB_PASSWORD=.*/m', "DB_PASSWORD={$password}", $envContent);

        // Si no existen, agregarlos
        if (!preg_match('/^DB_CONNECTION=/m', $envContent)) {
            $envContent .= "\nDB_CONNECTION={$driver}\n";
        }
        if (!preg_match('/^DB_HOST=/m', $envContent)) {
            $envContent .= "DB_HOST={$host}\n";
        }
        if (!preg_match('/^DB_PORT=/m', $envContent)) {
            $envContent .= "DB_PORT={$port}\n";
        }
        if (!preg_match('/^DB_DATABASE=/m', $envContent)) {
            $envContent .= "DB_DATABASE={$database}\n";
        }
        if (!preg_match('/^DB_USERNAME=/m', $envContent)) {
            $envContent .= "DB_USERNAME={$username}\n";
        }
        if (!preg_match('/^DB_PASSWORD=/m', $envContent)) {
            $envContent .= "DB_PASSWORD={$password}\n";
        }

        File::put($envPath, $envContent);

        // Limpiar caché de configuración
        $this->call('config:clear');

        $this->line('  ✓ Archivo .env actualizado');
        $this->line("  ✓ Conexión configurada: <fg=green>{$driver}</fg=green>");
        $this->newLine();

        // Verificar conexión
        if ($this->confirm('¿Deseas probar la conexión ahora?', true)) {
            $this->testDatabaseConnection($driver, $host, $port, $database, $username, $password);
        }
    }

    /**
     * Prueba la conexión a la base de datos.
     */
    protected function testDatabaseConnection(string $driver, string $host, string $port, string $database, string $username, ?string $password = null): void
    {
        $this->info('🔌 Probando conexión...');

        try {
            $config = [
                'driver' => $driver,
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password ?? '',
            ];

            if ($driver === 'mysql') {
                $config['charset'] = 'utf8mb4';
                $config['collation'] = 'utf8mb4_unicode_ci';
            } elseif ($driver === 'pgsql') {
                $config['charset'] = 'utf8';
            }

            Config::set("database.connections.test_connection", $config);

            DB::connection('test_connection')->getPdo();

            $this->line('  ✓ Conexión exitosa');
        } catch (\Exception $e) {
            $this->error('  ✗ Error de conexión: ' . $e->getMessage());
            $this->warn('  ⚠ Verifica las credenciales y asegúrate de que la base de datos exista.');
        }

        $this->newLine();
    }

    /**
     * Publica el archivo de configuración.
     */
    protected function publishConfiguration(): void
    {
        $this->info('📝 Publicando archivo de configuración...');

        try {
            $this->call('vendor:publish', [
                '--provider' => 'AngelitoSystems\FilamentTenancy\TenancyServiceProvider',
                '--tag' => 'filament-tenancy-config',
            ]);

            // Publicar también como config/tenancy.php según requerimiento
            $publishedConfig = config_path('filament-tenancy.php');
            $targetConfig = config_path('tenancy.php');

            if (File::exists($publishedConfig)) {
                if (!File::exists($targetConfig)) {
                    File::copy($publishedConfig, $targetConfig);
                    $this->line('  ✓ Archivo de configuración publicado como <fg=green>config/tenancy.php</fg=green>');
                } else {
                    // Si el archivo ya existe, preguntar si quiere sobrescribirlo
                    if ($this->confirm('  El archivo <fg=yellow>config/tenancy.php</fg=yellow> ya existe. ¿Deseas sobrescribirlo?', false)) {
                        File::copy($publishedConfig, $targetConfig);
                        $this->line('  ✓ Archivo <fg=green>config/tenancy.php</fg=green> sobrescrito');
                    } else {
                        $this->line('  ℹ Archivo <fg=yellow>config/tenancy.php</fg=yellow> conservado sin cambios');
                    }
                    $this->line('  ✓ Archivo de configuración disponible en <fg=green>config/filament-tenancy.php</fg=green>');
                }
            }
        } catch (\Exception $e) {
            $this->error('  ✗ Error al publicar la configuración: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Ejecuta las migraciones del paquete si el usuario confirma.
     */
    protected function runMigrations(): void
    {
        if (!$this->confirm('¿Deseas ejecutar las migraciones del paquete ahora?', true)) {
            $this->warn('  ⚠ Migraciones omitidas. Ejecuta <fg=yellow>php artisan migrate</fg=yellow> más tarde.');
            $this->newLine();
            return;
        }

        $this->info('🔄 Ejecutando migraciones...');

        $maxAttempts = 3;
        $attempt = 0;
        $success = false;

        while ($attempt < $maxAttempts && !$success) {
            $attempt++;
            
            try {
                $this->call('migrate');
                $this->line('  ✓ Migraciones ejecutadas correctamente');
                
                // Ejecutar seeder de planes después de las migraciones
                $this->runPlanSeeder();
                
                $success = true;
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $isConnectionError = str_contains($errorMessage, 'No se puede establecer una conexión') 
                    || str_contains($errorMessage, 'Connection refused')
                    || str_contains($errorMessage, 'denegó expresamente')
                    || str_contains($errorMessage, 'HY000')
                    || str_contains($errorMessage, 'SQLSTATE[HY000]');

                if ($isConnectionError && $attempt < $maxAttempts) {
                    $this->error("  ✗ Error de conexión (intento {$attempt}/{$maxAttempts}): " . $errorMessage);
                    $this->newLine();
                    
                    if ($this->confirm("  ¿Deseas reintentar la conexión?", true)) {
                        $this->line('  🔄 Reintentando...');
                        $this->newLine();
                        continue;
                    } else {
                        break;
                    }
                } else {
                    $this->error('  ✗ Error al ejecutar las migraciones: ' . $errorMessage);
                    
                    if ($isConnectionError && $attempt >= $maxAttempts) {
                        $this->newLine();
                        $this->warn('  ⚠ Se agotaron los intentos de conexión.');
                        
                        if ($this->confirm('  ¿Deseas limpiar la instalación y configurar la base de datos nuevamente?', false)) {
                            $this->cleanupInstallation();
                            return;
                        }
                    }
                    
                    $this->warn('  ⚠ Puedes ejecutar las migraciones manualmente con: <fg=yellow>php artisan migrate</fg=yellow>');
                    break;
                }
            }
        }

        $this->newLine();
    }

    /**
     * Ejecuta el seeder de planes.
     */
    protected function runPlanSeeder(): void
    {
        try {
            // Publicar el seeder primero si no existe
            $this->publishPlanSeeder();
            
            // Intentar ejecutar desde Database\Seeders (si fue publicado)
            $seederClass = 'Database\\Seeders\\PlanSeeder';
            
            if (class_exists($seederClass)) {
                $this->call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                ]);
                $this->line('  ✓ Planes creados exitosamente');
            } else {
                // Si no existe, usar el seeder del paquete directamente
                $packageSeeder = \AngelitoSystems\FilamentTenancy\Database\Seeders\PlanSeeder::class;
                if (class_exists($packageSeeder)) {
                    $seeder = new $packageSeeder();
                    $seeder->setCommand($this);
                    $seeder->run();
                    $this->line('  ✓ Planes creados exitosamente');
                } else {
                    $this->warn('  ⚠ No se encontró el seeder de planes');
                }
            }
        } catch (\Exception $e) {
            $this->warn('  ⚠ No se pudieron crear los planes: ' . $e->getMessage());
            $this->line('  Puedes ejecutar el seeder manualmente con: <fg=yellow>php artisan db:seed --class=Database\\Seeders\\PlanSeeder</fg=yellow>');
        }
    }

    /**
     * Publica el seeder de planes con el namespace correcto.
     */
    protected function publishPlanSeeder(): void
    {
        try {
            $sourceSeeder = __DIR__ . '/../../database/seeders/PlanSeeder.php';
            $targetSeeder = database_path('seeders/PlanSeeder.php');
            
            // Si ya existe, no sobrescribir (permitir personalización)
            if (File::exists($targetSeeder)) {
                return;
            }
            
            // Asegurar que el directorio existe
            if (!File::exists(database_path('seeders'))) {
                File::makeDirectory(database_path('seeders'), 0755, true);
            }
            
            if (File::exists($sourceSeeder)) {
                $content = File::get($sourceSeeder);
                // Cambiar el namespace al namespace de publicación
                $content = str_replace(
                    'namespace AngelitoSystems\\FilamentTenancy\\Database\\Seeders;',
                    'namespace Database\\Seeders;',
                    $content
                );
                File::put($targetSeeder, $content);
            }
        } catch (\Exception $e) {
            // Silenciar errores de publicación, se intentará usar el seeder del paquete
        }
    }

    /**
     * Limpia la instalación en caso de error crítico.
     */
    protected function cleanupInstallation(): void
    {
        $this->newLine();
        $this->info('🧹 Limpiando instalación...');

        try {
            // Eliminar archivos de configuración publicados
            $configFiles = [
                config_path('filament-tenancy.php'),
                config_path('tenancy.php'),
            ];

            foreach ($configFiles as $configFile) {
                if (File::exists($configFile)) {
                    File::delete($configFile);
                    $this->line("  ✓ Eliminado: {$configFile}");
                }
            }

            // Remover ServiceProvider si fue agregado automáticamente
            $this->removeServiceProvider();

            $this->line('  ✓ Instalación limpiada');
            $this->newLine();
            $this->warn('  ⚠ La instalación fue limpiada debido a errores de conexión.');
            $this->line('  Ejecuta <fg=yellow>php artisan filament-tenancy:install</fg=yellow> nuevamente después de configurar la base de datos correctamente.');
            $this->newLine();
        } catch (\Exception $e) {
            $this->error('  ✗ Error al limpiar la instalación: ' . $e->getMessage());
            $this->newLine();
        }
    }

    /**
     * Remueve el ServiceProvider del registro.
     */
    protected function removeServiceProvider(): void
    {
        $providerClass = 'AngelitoSystems\\FilamentTenancy\\TenancyServiceProvider';
        $providersPath = base_path('bootstrap/providers.php');
        $appConfigPath = base_path('config/app.php');

        // Laravel 11: bootstrap/providers.php
        if (File::exists($providersPath)) {
            $content = File::get($providersPath);
            $originalContent = $content;

            // Remover el provider
            $content = preg_replace(
                '/\s*' . preg_quote($providerClass, '/') . '::class,?\s*/',
                '',
                $content
            );

            if ($content !== $originalContent) {
                File::put($providersPath, $content);
                $this->line('  ✓ ServiceProvider removido de bootstrap/providers.php');
            }
        }

        // Laravel 10: config/app.php
        if (File::exists($appConfigPath)) {
            $content = File::get($appConfigPath);
            $originalContent = $content;

            // Remover del array de providers
            $content = preg_replace(
                '/\s*' . preg_quote($providerClass . '::class', '/') . ',?\s*/',
                '',
                $content
            );

            if ($content !== $originalContent) {
                File::put($appConfigPath, $content);
                $this->line('  ✓ ServiceProvider removido de config/app.php');
            }
        }
    }

    /**
     * Registra el TenancyServiceProvider en config/app.php si no está agregado.
     */
    protected function registerServiceProvider(): void
    {
        $this->info('🔧 Verificando registro del ServiceProvider...');

        $providerClass = 'AngelitoSystems\\FilamentTenancy\\TenancyServiceProvider';
        $providerString = $providerClass . '::class';

        // Detectar versión de Laravel
        $laravelVersion = (int) app()->version();
        $providersPath = base_path('bootstrap/providers.php');
        $appConfigPath = base_path('config/app.php');

        // Laravel 11: Priorizar bootstrap/providers.php
        if ($laravelVersion >= 11 || File::exists($providersPath)) {
            if (File::exists($providersPath)) {
                $this->registerInProvidersFile($providersPath, $providerClass);
            } else {
                // Crear bootstrap/providers.php si no existe en Laravel 11
                $this->createProvidersFile($providersPath, $providerClass);
            }
            return;
        }

        // Laravel 10 y anteriores: usar config/app.php
        if (File::exists($appConfigPath)) {
            $this->registerInAppConfig($appConfigPath, $providerString);
            return;
        }

        // Si no se encuentra ningún archivo, verificar si está auto-descubierto
        $this->line('  ℹ El ServiceProvider se registrará automáticamente mediante auto-discovery de Composer.');
        $this->newLine();
    }

    /**
     * Registra el provider en config/app.php.
     */
    protected function registerInAppConfig(string $configPath, string $providerString): void
    {
        $content = File::get($configPath);

        // Verificar si ya está registrado (buscar tanto con ::class como sin él)
        $providerClass = 'AngelitoSystems\\FilamentTenancy\\TenancyServiceProvider';
        if (Str::contains($content, $providerString) || Str::contains($content, $providerClass)) {
            $this->line('  ✓ TenancyServiceProvider ya está registrado en <fg=green>config/app.php</fg=green>');
            $this->newLine();
            return;
        }

        // Buscar el array de providers usando múltiples patrones
        $patterns = [
            "/'providers'\s*=>\s*\[(.*?)\]/s",  // Patrón estándar
            "/\"providers\"\s*=>\s*\[(.*?)\]/s", // Con comillas dobles
            "/'providers'\s*=>\s*ServiceProvider::defaultProviders\(\)->merge\(\[(.*?)\]\)/s", // Laravel 11 con merge
        ];

        $providersArray = null;
        $fullMatch = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $providersArray = $matches[1] ?? null;
                $fullMatch = $matches[0] ?? null;
                break;
            }
        }

        if ($providersArray !== null) {
            // Buscar el último provider en el array para insertar después
            $lines = explode("\n", $providersArray);
            $lastProviderLine = null;
            $lastProviderLineIndex = 0;
            
            // Encontrar la última línea con un provider
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if (preg_match("/([A-Za-z0-9\\\]+::class),?\s*$/", $line)) {
                    $lastProviderLine = $line;
                    $lastProviderLineIndex = $i;
                    break;
                }
            }
            
            if ($lastProviderLine !== null) {
                // Insertar después del último provider
                $indentation = str_repeat(' ', 8); // 8 espacios de indentación estándar
                $newProviderLine = $indentation . $providerString . ',';
                
                // Encontrar la línea completa con indentación
                $fullLine = $lines[$lastProviderLineIndex];
                $replacement = $fullLine . "\n" . $newProviderLine;
                
                $content = str_replace($fullLine, $replacement, $content);
                File::put($configPath, $content);
                $this->line('  ✓ TenancyServiceProvider registrado en <fg=green>config/app.php</fg=green>');
            } else {
                // Si no encuentra providers, agregar después de la apertura del array
                $content = preg_replace(
                    "/('providers'\s*=>\s*\[)/",
                    "$1\n        " . $providerString . ',',
                    $content
                );
                File::put($configPath, $content);
                $this->line('  ✓ TenancyServiceProvider registrado en <fg=green>config/app.php</fg=green>');
            }
        } else {
            // Intentar agregar después de App\Providers\RouteServiceProvider::class
            if (preg_match('/(App\\\\Providers\\\\RouteServiceProvider::class,?)/', $content, $matches)) {
                $replacement = $matches[1] . "\n        " . $providerString . ',';
                $content = str_replace($matches[1], $replacement, $content);
                File::put($configPath, $content);
                $this->line('  ✓ TenancyServiceProvider registrado en <fg=green>config/app.php</fg=green>');
            } else {
                $this->warn('  ⚠ No se pudo encontrar el array de providers en config/app.php');
                $this->warn('  ⚠ El ServiceProvider se registrará automáticamente mediante auto-discovery de Composer.');
            }
        }

        $this->newLine();
    }

    /**
     * Crea el archivo bootstrap/providers.php si no existe (Laravel 11).
     */
    protected function createProvidersFile(string $providersPath, string $providerClass): void
    {
        $directory = dirname($providersPath);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = "<?php\n\nreturn [\n    " . $providerClass . "::class,\n];\n";
        File::put($providersPath, $content);
        $this->line('  ✓ Archivo <fg=green>bootstrap/providers.php</fg=green> creado');
        $this->line('  ✓ TenancyServiceProvider registrado en <fg=green>bootstrap/providers.php</fg=green>');
        $this->newLine();
    }

    /**
     * Registra el provider en bootstrap/providers.php (Laravel 11).
     */
    protected function registerInProvidersFile(string $providersPath, string $providerClass): void
    {
        $content = File::get($providersPath);

        // Verificar si ya está registrado (buscar con ::class o sin él)
        $providerString = $providerClass . '::class';
        if (Str::contains($content, $providerClass) || Str::contains($content, $providerString)) {
            $this->line('  ✓ TenancyServiceProvider ya está registrado en <fg=green>bootstrap/providers.php</fg=green>');
            $this->newLine();
            return;
        }

        // Si el archivo ya tiene un return array, agregar el provider al array
        if (preg_match('/return\s+\[(.*?)\];/s', $content, $matches)) {
            $providersArray = $matches[1];
            
            // Buscar la última línea del array (ignorar comentarios y líneas vacías)
            $lines = explode("\n", $providersArray);
            $lastLine = null;
            $lastLineIndex = -1;
            
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                // Buscar líneas con providers (::class o sin él)
                if (!empty($line) && 
                    !preg_match('/^\s*\/\//', $line) && 
                    (preg_match('/::class/', $line) || preg_match('/^[A-Za-z0-9\\\]+$/', $line))) {
                    $lastLine = $lines[$i];
                    $lastLineIndex = $i;
                    break;
                }
            }
            
            if ($lastLine !== null && $lastLineIndex >= 0) {
                // Agregar después de la última línea no vacía
                $indentation = str_repeat(' ', 4);
                $newProviderLine = $indentation . $providerString . ',';
                
                // Si la última línea no termina con coma, agregarla
                $lineToReplace = rtrim($lastLine);
                if (!Str::endsWith($lineToReplace, ',')) {
                    $lineToReplace .= ',';
                }
                
                $replacement = $lineToReplace . "\n" . $newProviderLine;
                $content = str_replace($lastLine, $replacement, $content);
            } else {
                // Si el array está vacío o solo tiene comentarios, agregar directamente
                $content = preg_replace(
                    '/return\s+\[(.*?)\];/s',
                    "return [\n    " . $providerString . ",\n];",
                    $content
                );
            }
        } else {
            // Si no hay return, agregar uno nuevo al final del archivo
            $content = trim($content);
            if (!empty($content) && !Str::endsWith($content, "\n")) {
                $content .= "\n";
            }
            $content .= "\nreturn [\n    " . $providerString . ",\n];\n";
        }

        File::put($providersPath, $content);
        $this->line('  ✓ TenancyServiceProvider registrado en <fg=green>bootstrap/providers.php</fg=green>');
        $this->newLine();
    }

    /**
     * Registra los middlewares necesarios en bootstrap/app.php (Laravel 11).
     */
    protected function registerMiddlewares(): void
    {
        // Solo para Laravel 11
        $laravelVersion = (int) app()->version();
        if ($laravelVersion < 11) {
            return; // Laravel 10 registra middlewares automáticamente a través del ServiceProvider
        }

        $bootstrapAppPath = base_path('bootstrap/app.php');
        
        if (!File::exists($bootstrapAppPath)) {
            return;
        }

        $this->info('🔧 Registrando middlewares en bootstrap/app.php...');

        $content = File::get($bootstrapAppPath);
        $originalContent = $content;

        // Verificar si ya están registrados los middlewares
        $middlewareClass = 'AngelitoSystems\\FilamentTenancy\\Middleware\\InitializeTenancy';
        if (str_contains($content, $middlewareClass)) {
            $this->line('  ✓ Middlewares ya están registrados en bootstrap/app.php');
            return;
        }

        // Buscar el bloque withMiddleware
        $pattern = '/->withMiddleware\s*\(\s*function\s*\(\s*Middleware\s+\$middleware\s*\):\s*void\s*\{([^}]*)\}\s*\)/s';
        
        if (preg_match($pattern, $content, $matches)) {
            // Middleware ya existe, agregar el registro
            $middlewareBlock = $matches[0];
            $middlewareContent = $matches[1];
            
            // Verificar si está vacío o solo tiene comentarios
            $trimmedContent = trim($middlewareContent);
            if (empty($trimmedContent) || $trimmedContent === '//') {
                // Reemplazar el comentario o bloque vacío con el registro del middleware
                $newMiddlewareBlock = str_replace(
                    $middlewareContent,
                    "\n        \$middleware->web(append: [\n            \\{$middlewareClass}::class,\n        ]);",
                    $middlewareBlock
                );
            } else {
                // Agregar al final del bloque existente
                $newMiddlewareBlock = str_replace(
                    '}',
                    "        \$middleware->web(append: [\n            \\{$middlewareClass}::class,\n        ]);\n    }",
                    $middlewareBlock
                );
            }
            
            $content = str_replace($middlewareBlock, $newMiddlewareBlock, $content);
        } else {
            // No existe el bloque, agregarlo después de withRouting
            $withRoutingPattern = '/->withRouting\s*\([^)]+\)/s';
            if (preg_match($withRoutingPattern, $content, $routingMatch)) {
                $middlewareRegistration = "\n    ->withMiddleware(function (Middleware \$middleware): void {\n        \$middleware->web(append: [\n            \\{$middlewareClass}::class,\n        ]);\n    })";
                $content = str_replace($routingMatch[0], $routingMatch[0] . $middlewareRegistration, $content);
            }
        }

        if ($content !== $originalContent) {
            File::put($bootstrapAppPath, $content);
            $this->line('  ✓ Middlewares registrados en <fg=green>bootstrap/app.php</fg=green>');
        } else {
            $this->warn('  ⚠ No se pudo registrar los middlewares automáticamente.');
            $this->line('  Por favor, agrega manualmente en bootstrap/app.php:');
            $this->line("  \$middleware->web(append: [\\{$middlewareClass}::class]);");
        }
    }

    /**
     * Publica los componentes y vistas de 404 personalizadas.
     */
    protected function publish404Components(): void
    {
        // Solo para Laravel 11
        $laravelVersion = (int) app()->version();
        if ($laravelVersion < 11) {
            return; // Laravel 10 maneja 404 de forma diferente
        }

        $this->info('🎨 Configurando página 404 personalizada...');

        // Preguntar si quiere publicar los componentes
        if (!$this->confirm('¿Deseas publicar los componentes y vistas de la página 404 personalizada?', true)) {
            $this->line('  ℹ Los componentes y vistas se usarán desde el paquete.');
            $this->register404View();
            return;
        }

        // Publicar vistas
        try {
            $this->call('vendor:publish', [
                '--provider' => 'AngelitoSystems\FilamentTenancy\TenancyServiceProvider',
                '--tag' => 'filament-tenancy-views',
            ]);
            $this->line('  ✓ Vistas publicadas en <fg=green>resources/views/vendor/filament-tenancy</fg=green>');
        } catch (\Exception $e) {
            $this->warn('  ⚠ No se pudieron publicar las vistas: ' . $e->getMessage());
        }

        // Publicar componente Livewire (opcional, solo si Livewire está disponible)
        if (class_exists(\Livewire\Component::class)) {
            try {
                $this->call('vendor:publish', [
                    '--provider' => 'AngelitoSystems\FilamentTenancy\TenancyServiceProvider',
                    '--tag' => 'filament-tenancy-components',
                ]);
                
                // Actualizar el namespace del componente publicado
                $componentPath = app_path('Livewire/TenantNotFound.php');
                if (File::exists($componentPath)) {
                    $content = File::get($componentPath);
                    $content = str_replace(
                        'namespace AngelitoSystems\\FilamentTenancy\\Components;',
                        'namespace App\\Livewire;',
                        $content
                    );
                    File::put($componentPath, $content);
                    $this->line('  ✓ Componente Livewire publicado en <fg=green>app/Livewire/TenantNotFound.php</fg=green>');
                    $this->line('  ℹ Puedes personalizar el componente según tus necesidades.');
                }
            } catch (\Exception $e) {
                $this->warn('  ⚠ No se pudo publicar el componente: ' . $e->getMessage());
            }
        } else {
            $this->line('  ℹ Livewire no está disponible. La vista funcionará sin componente Livewire.');
        }

        // Registrar la vista 404 en bootstrap/app.php
        $this->register404View();
    }

    /**
     * Registra la vista 404 en bootstrap/app.php (Laravel 11).
     */
    protected function register404View(): void
    {
        $laravelVersion = (int) app()->version();
        if ($laravelVersion < 11) {
            return;
        }

        $bootstrapAppPath = base_path('bootstrap/app.php');
        
        if (!File::exists($bootstrapAppPath)) {
            return;
        }

        $content = File::get($bootstrapAppPath);
        $originalContent = $content;

        // Verificar si ya está registrada la vista 404
        if (str_contains($content, 'tenant-not-found') || str_contains($content, 'TenantNotFound')) {
            $this->line('  ✓ Vista 404 ya está registrada en bootstrap/app.php');
            return;
        }

        // Buscar el bloque withExceptions
        $pattern = '/->withExceptions\s*\(\s*function\s*\(\s*Exceptions\s+\$exceptions\s*\):\s*void\s*\{([^}]*)\}\s*\)/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $exceptionsBlock = $matches[0];
            $exceptionsContent = $matches[1];
            
            // Verificar si está vacío o solo tiene comentarios
            $trimmedContent = trim($exceptionsContent);
            if (empty($trimmedContent) || $trimmedContent === '//') {
                // Reemplazar el comentario o bloque vacío
                $exceptionRegistration = "\n        \$exceptions->render(function (\\Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException \$e, \\Illuminate\\Http\\Request \$request) {\n            if (str_contains(\$e->getMessage(), 'Tenant not found')) {\n                return response()->view('filament-tenancy::errors.tenant-not-found', [\n                    'host' => \$request->getHost(),\n                    'resolver' => config('filament-tenancy.resolver', 'domain'),\n                    'appDomain' => env('APP_DOMAIN'),\n                ], 404);\n            }\n        });";
                
                $newExceptionsBlock = str_replace(
                    $exceptionsContent,
                    $exceptionRegistration,
                    $exceptionsBlock
                );
            } else {
                // Agregar al final del bloque existente
                $exceptionRegistration = "\n        \$exceptions->render(function (\\Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException \$e, \\Illuminate\\Http\\Request \$request) {\n            if (str_contains(\$e->getMessage(), 'Tenant not found')) {\n                return response()->view('filament-tenancy::errors.tenant-not-found', [\n                    'host' => \$request->getHost(),\n                    'resolver' => config('filament-tenancy.resolver', 'domain'),\n                    'appDomain' => env('APP_DOMAIN'),\n                ], 404);\n            }\n        });";
                
                $newExceptionsBlock = str_replace(
                    '}',
                    $exceptionRegistration . "\n    }",
                    $exceptionsBlock
                );
            }
            
            $content = str_replace($exceptionsBlock, $newExceptionsBlock, $content);
        } else {
            // No existe el bloque, agregarlo después de withMiddleware
            $withMiddlewarePattern = '/->withMiddleware\s*\([^)]+\)/s';
            if (preg_match($withMiddlewarePattern, $content, $middlewareMatch)) {
                $exceptionRegistration = "\n    ->withExceptions(function (Exceptions \$exceptions): void {\n        \$exceptions->render(function (\\Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException \$e, \\Illuminate\\Http\\Request \$request) {\n            if (str_contains(\$e->getMessage(), 'Tenant not found')) {\n                return response()->view('filament-tenancy::errors.tenant-not-found', [\n                    'host' => \$request->getHost(),\n                    'resolver' => config('filament-tenancy.resolver', 'domain'),\n                    'appDomain' => env('APP_DOMAIN'),\n                ], 404);\n            }\n        });\n    })";
                $content = str_replace($middlewareMatch[0], $middlewareMatch[0] . $exceptionRegistration, $content);
            }
        }

        if ($content !== $originalContent) {
            File::put($bootstrapAppPath, $content);
            $this->line('  ✓ Vista 404 registrada en <fg=green>bootstrap/app.php</fg=green>');
        } else {
            $this->warn('  ⚠ No se pudo registrar la vista 404 automáticamente.');
            $this->line('  Puedes agregarla manualmente en bootstrap/app.php en el bloque withExceptions.');
        }
    }

    /**
     * Muestra el mensaje de éxito final.
     */
    protected function displaySuccessMessage(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════════╗');
        $this->line('║                                                               ║');
        $this->line('║        <fg=green>✓ Instalación completada exitosamente</fg=green>               ║');
        $this->line('║                                                               ║');
        $this->line('╚═══════════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        $this->info('¡Filament Tenancy ha sido instalado correctamente!');
        $this->newLine();
        
        $this->line('Próximos pasos:');
        $this->line('  1. Revisa la configuración en <fg=cyan>config/tenancy.php</fg=cyan> o <fg=cyan>config/filament-tenancy.php</fg=cyan>');
        $this->line('  2. Configura tus dominios centrales en la configuración');
        $this->line('  3. Crea tu primer tenant con: <fg=yellow>php artisan tenancy:create</fg=yellow>');
        $this->newLine();
    }
}

