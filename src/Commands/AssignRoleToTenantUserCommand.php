<?php

namespace AngelitoSystems\FilamentTenancy\Commands;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Models\Role;
use AngelitoSystems\FilamentTenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AssignRoleToTenantUserCommand extends Command
{
    protected ?Tenant $currentTenant = null;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:user-assign-role 
                            {--tenant= : The tenant ID or slug}
                            {--user= : The user ID or email}
                            {--role= : The role slug(s), comma-separated for multiple roles}
                            {--sync : Replace all existing roles with the new ones}
                            {--list-tenants : List all available tenants}
                            {--list-users : List all users in tenant}
                            {--list-roles : List all available roles in tenant}';

    /**
     * The console command description.
     */
    protected $description = 'Assign role(s) to a user in a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayBranding();

        // Handle list options
        if ($this->option('list-tenants')) {
            $this->listTenants();
            return self::SUCCESS;
        }

        if ($this->option('list-users')) {
            $this->listUsers();
            return self::SUCCESS;
        }

        if ($this->option('list-roles')) {
            $this->listRoles();
            return self::SUCCESS;
        }

        // Get tenant
        $tenant = $this->getTenant();
        if (!$tenant) {
            return self::FAILURE;
        }
        
        // Store tenant for use in other methods
        $this->currentTenant = $tenant;

        // Get user
        $user = $this->getUser();
        if (!$user) {
            return self::FAILURE;
        }

        // Get roles
        $roles = $this->getRoles();
        if (empty($roles)) {
            return self::FAILURE;
        }

        // Assign roles
        try {
            $this->assignRolesToUser($user, $roles);
            
            $this->newLine();
            $this->info("✓ Roles asignados exitosamente al usuario '{$user->name}' en el tenant '{$this->currentTenant->name}'!");
            
            $this->displayUserRoles($user);
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Error al asignar roles: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Display branding.
     */
    protected function displayBranding(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════════╗');
        $this->line('║                                                               ║');
        $this->line('║        <fg=cyan>Filament Tenancy</fg=cyan> - Assign Roles to User          ║');
        $this->line('║                  <fg=yellow>Angelito Systems</fg=yellow>                      ║');
        $this->line('║                                                               ║');
        $this->line('╚═══════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    /**
     * Get the tenant.
     */
    protected function getTenant(): ?Tenant
    {
        $tenantIdentifier = $this->option('tenant');
        
        if (!$tenantIdentifier) {
            $this->info('🔍 Selección de tenant');
            $this->newLine();
            
            $tenants = Tenancy::getAllTenants();
            
            if ($tenants->isEmpty()) {
                $this->error('No hay tenants disponibles. Crea un tenant primero con:');
                $this->line('  <fg=yellow>php artisan tenancy:create</fg=yellow>');
                return null;
            }
            
            $choices = [];
            $tenantMap = [];
            $index = 1;
            
            foreach ($tenants as $tenant) {
                $choiceKey = (string) $index;
                $choices[$choiceKey] = "{$tenant->name} ({$tenant->slug}) - " . ($tenant->domain ?: $tenant->subdomain);
                $tenantMap[$choiceKey] = $tenant;
                $index++;
            }
            
            $selectedKey = $this->choice('Selecciona un tenant', $choices);
            $tenant = $tenantMap[$selectedKey] ?? null;
        } else {
            // Try to find by ID first, then by slug
            $tenant = Tenancy::findTenant((int) $tenantIdentifier);
            if (!$tenant) {
                $tenant = Tenancy::findTenantBySlug($tenantIdentifier);
            }
            
            if (!$tenant) {
                $this->error("Tenant '{$tenantIdentifier}' no encontrado.");
                $this->line('Usa <fg=yellow>--list-tenants</fg=yellow> para ver los tenants disponibles.');
                return null;
            }
        }
        
        return $tenant;
    }

    /**
     * Get the user.
     */
    protected function getUser(): ?Model
    {
        $userIdentifier = $this->option('user');
        
        if (!$this->currentTenant) {
            $this->error('No hay tenant seleccionado.');
            return null;
        }

        // Get User model class from config
        $userModelClass = config('filament-tenancy.user_model', config('auth.providers.users.model', 'App\\Models\\User'));
        
        if (!class_exists($userModelClass)) {
            $this->error("User model class '{$userModelClass}' not found. Please check your configuration.");
            return null;
        }

        return Tenancy::runForTenant($this->currentTenant, function () use ($userIdentifier, $userModelClass) {
            if (!$userIdentifier) {
                $this->info('👤 Selección de usuario');
                $this->newLine();
                
                $users = $userModelClass::all();
                
                if ($users->isEmpty()) {
                    $this->error('No hay usuarios disponibles en este tenant.');
                    return null;
                }
                
                $choices = [];
                $userMap = [];
                $index = 1;
                
                foreach ($users as $user) {
                    $choiceKey = (string) $index;
                    $choices[$choiceKey] = "{$user->name} ({$user->email})";
                    $userMap[$choiceKey] = $user;
                    $index++;
                }
                
                $selectedKey = $this->choice('Selecciona un usuario', $choices);
                return $userMap[$selectedKey] ?? null;
            } else {
                // Try to find by ID first, then by email
                $user = $userModelClass::find($userIdentifier);
                if (!$user) {
                    $user = $userModelClass::where('email', $userIdentifier)->first();
                }
                
                if (!$user) {
                    $this->error("Usuario '{$userIdentifier}' no encontrado en este tenant.");
                    $this->line('Usa <fg=yellow>--list-users</fg=yellow> para ver los usuarios disponibles.');
                    return null;
                }
                
                return $user;
            }
        });
    }

    /**
     * Get roles to assign.
     */
    protected function getRoles(): array
    {
        if (!$this->currentTenant) {
            return [];
        }

        $roleSlugs = $this->option('role');
        
        if ($roleSlugs) {
            // Parse comma-separated role slugs
            $slugs = array_map('trim', explode(',', $roleSlugs));
            $roles = [];
            
            foreach ($slugs as $slug) {
                $role = $this->getRoleBySlug($slug);
                if ($role) {
                    $roles[] = $role;
                } else {
                    $this->warn("Rol '{$slug}' no encontrado y será omitido.");
                }
            }
            
            return $roles;
        }

        // Interactive selection
        return $this->selectRolesInteractively();
    }

    /**
     * Select roles interactively.
     */
    protected function selectRolesInteractively(): array
    {
        if (!$this->currentTenant) {
            return [];
        }

        // Get roles in tenant context
        $roles = Tenancy::runForTenant($this->currentTenant, function () {
            return Role::all();
        });
        
        if ($roles->isEmpty()) {
            $this->error('No hay roles disponibles. Asegúrate de que el tenant tenga roles creados.');
            return [];
        }
        
        $this->newLine();
        $this->info('Roles disponibles:');
        $choices = [];
        $roleMap = [];
        $index = 1;
        
        foreach ($roles as $role) {
            $choiceKey = (string) $index;
            $choices[$choiceKey] = "{$role->name} ({$role->slug})";
            $roleMap[$choiceKey] = $role;
            $index++;
        }
        
        $this->newLine();
        $selectedKeys = $this->choice(
            'Selecciona uno o más roles (separa múltiples con comas)',
            $choices,
            null,
            null,
            true
        );
        
        $selectedRoles = [];
        foreach ($selectedKeys as $key) {
            if (isset($roleMap[$key])) {
                $selectedRoles[] = $roleMap[$key];
            }
        }
        
        return $selectedRoles;
    }

    /**
     * Get role by slug in tenant context.
     */
    protected function getRoleBySlug(string $slug): ?Role
    {
        if (!$this->currentTenant) {
            return null;
        }

        return Tenancy::runForTenant($this->currentTenant, function () use ($slug) {
            return Role::where('slug', $slug)->first();
        });
    }

    /**
     * Assign roles to user.
     */
    protected function assignRolesToUser(Model $user, array $roles): void
    {
        Tenancy::runForTenant($this->currentTenant, function () use ($user, $roles) {
            if ($this->option('sync')) {
                // Replace all existing roles
                if (method_exists($user, 'syncRoles')) {
                    $user->syncRoles($roles);
                    $this->info("  ✓ Roles sincronizados (reemplazados todos los roles existentes).");
                } elseif (method_exists($user, 'roles')) {
                    $user->roles()->sync(collect($roles)->pluck('id')->toArray());
                    $this->info("  ✓ Roles sincronizados (reemplazados todos los roles existentes).");
                } else {
                    // Fallback: delete all and insert new ones
                    $userModelClass = get_class($user);
                    DB::table('model_has_roles')
                        ->where('model_type', $userModelClass)
                        ->where('model_id', $user->id)
                        ->delete();
                    
                    foreach ($roles as $role) {
                        DB::table('model_has_roles')->insertOrIgnore([
                            'role_id' => $role->id,
                            'model_type' => $userModelClass,
                            'model_id' => $user->id,
                        ]);
                    }
                    $this->info("  ✓ Roles sincronizados (usando inserción directa).");
                }
            } else {
                // Add roles (don't remove existing ones)
                foreach ($roles as $role) {
                    if (method_exists($user, 'assignRole')) {
                        $user->assignRole($role);
                        $this->info("  ✓ Rol '{$role->name}' asignado correctamente.");
                    } elseif (method_exists($user, 'roles')) {
                        if (!$user->roles()->where('role_id', $role->id)->exists()) {
                            $user->roles()->attach($role->id);
                            $this->info("  ✓ Rol '{$role->name}' asignado correctamente (usando relación directa).");
                        } else {
                            $this->warn("  ⚠ El usuario ya tiene el rol '{$role->name}'.");
                        }
                    } else {
                        // Fallback: insert directly into pivot table
                        $userModelClass = get_class($user);
                        $exists = DB::table('model_has_roles')
                            ->where('role_id', $role->id)
                            ->where('model_type', $userModelClass)
                            ->where('model_id', $user->id)
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('model_has_roles')->insertOrIgnore([
                                'role_id' => $role->id,
                                'model_type' => $userModelClass,
                                'model_id' => $user->id,
                            ]);
                            $this->info("  ✓ Rol '{$role->name}' asignado correctamente (usando inserción directa).");
                        } else {
                            $this->warn("  ⚠ El usuario ya tiene el rol '{$role->name}'.");
                        }
                    }
                }
            }
            
            // Refresh user to load relationships
            $user->refresh();
            if (method_exists($user, 'load')) {
                try {
                    $user->load('roles');
                } catch (\Exception $e) {
                    // Relationships might not exist, that's okay
                }
            }
        });
    }

    /**
     * Display user roles information.
     */
    protected function displayUserRoles(Model $user): void
    {
        $this->newLine();
        $this->table(
            ['Propiedad', 'Valor'],
            [
                ['Tenant', $this->currentTenant->name . ' (' . $this->currentTenant->slug . ')'],
                ['Usuario', $user->name . ' (' . $user->email . ')'],
                ['Roles asignados', $this->getUserRolesList($user)],
            ]
        );
    }

    /**
     * Get user roles as comma-separated list.
     */
    protected function getUserRolesList(Model $user): string
    {
        return Tenancy::runForTenant($this->currentTenant, function () use ($user) {
            if (method_exists($user, 'roles')) {
                try {
                    $roles = $user->roles;
                    if ($roles && $roles->count() > 0) {
                        return $roles->pluck('name')->join(', ');
                    }
                } catch (\Exception $e) {
                    // Try direct query
                }
            }
            
            // Fallback: query directly
            $userModelClass = get_class($user);
            $roleIds = DB::table('model_has_roles')
                ->where('model_type', $userModelClass)
                ->where('model_id', $user->id)
                ->pluck('role_id');
            
            if ($roleIds->isEmpty()) {
                return 'Ninguno';
            }
            
            $roles = Role::whereIn('id', $roleIds)->get();
            return $roles->pluck('name')->join(', ');
        });
    }

    /**
     * List all tenants.
     */
    protected function listTenants(): void
    {
        $this->info('📋 Lista de tenants disponibles:');
        $this->newLine();
        
        $tenants = Tenancy::getAllTenants();
        
        if ($tenants->isEmpty()) {
            $this->line('No hay tenants disponibles.');
            return;
        }
        
        $data = [];
        foreach ($tenants as $tenant) {
            $data[] = [
                'ID' => $tenant->id,
                'Nombre' => $tenant->name,
                'Slug' => $tenant->slug,
                'Dominio' => $tenant->domain ?: $tenant->subdomain,
                'Activo' => $tenant->is_active ? 'Sí' : 'No',
            ];
        }
        
        $this->table(['ID', 'Nombre', 'Slug', 'Dominio', 'Activo'], $data);
    }

    /**
     * List users in tenant context.
     */
    protected function listUsers(): void
    {
        $tenant = $this->getTenant();
        if (!$tenant) {
            return;
        }
        
        $this->info("📋 Usuarios disponibles en el tenant '{$tenant->name}':");
        $this->newLine();
        
        Tenancy::runForTenant($tenant, function () {
            $userModelClass = config('filament-tenancy.user_model', config('auth.providers.users.model', 'App\\Models\\User'));
            
            if (!class_exists($userModelClass)) {
                $this->error("User model class '{$userModelClass}' not found.");
                return;
            }
            
            $users = $userModelClass::all();
            
            if ($users->isEmpty()) {
                $this->line('No hay usuarios disponibles en este tenant.');
                return;
            }
            
            $data = [];
            foreach ($users as $user) {
                $roles = 'N/A';
                if (method_exists($user, 'roles')) {
                    try {
                        $userRoles = $user->roles;
                        if ($userRoles && $userRoles->count() > 0) {
                            $roles = $userRoles->pluck('name')->join(', ');
                        }
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
                
                $data[] = [
                    'ID' => $user->id,
                    'Nombre' => $user->name,
                    'Email' => $user->email,
                    'Roles' => $roles,
                ];
            }
            
            $this->table(['ID', 'Nombre', 'Email', 'Roles'], $data);
        });
    }

    /**
     * List roles in tenant context.
     */
    protected function listRoles(): void
    {
        $tenant = $this->getTenant();
        if (!$tenant) {
            return;
        }
        
        $this->info("📋 Roles disponibles en el tenant '{$tenant->name}':");
        $this->newLine();
        
        Tenancy::runForTenant($tenant, function () {
            $roles = Role::with('permissions')->get();
            
            if ($roles->isEmpty()) {
                $this->line('No hay roles disponibles en este tenant.');
                return;
            }
            
            $data = [];
            foreach ($roles as $role) {
                $data[] = [
                    'Nombre' => $role->name,
                    'Slug' => $role->slug,
                    'Permisos' => $role->permissions->count(),
                    'Descripción' => $role->description ?? 'N/A',
                ];
            }
            
            $this->table(['Nombre', 'Slug', 'Permisos', 'Descripción'], $data);
        });
    }
}

