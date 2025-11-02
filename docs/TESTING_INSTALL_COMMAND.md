# Cómo Probar el Comando `filament-tenancy:install`

Este documento explica cómo probar el comando de instalación en un proyecto Laravel real.

## Método Recomendado: Usar Path Repository

Esta es la forma más fácil y estándar de probar el paquete durante el desarrollo local.

### Paso 1: Preparar tu Proyecto Laravel

En tu proyecto Laravel de prueba, edita el archivo `composer.json` y agrega el repositorio local en la sección `repositories`:

**Ejemplo para Windows (ruta absoluta):**
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "C:/Proyectos/Angelito Systems/packages/filament-tenancy"
        }
    ]
}
```

**Ejemplo para ruta relativa:**
Si tu proyecto Laravel está en `C:/Proyectos/MiProyecto` y el paquete está en `C:/Proyectos/Angelito Systems/packages/filament-tenancy`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../Angelito Systems/packages/filament-tenancy"
        }
    ]
}
```

**⚠️ Importante en Windows:** Usa barras normales `/` o barras invertidas dobles `\\` en las rutas, no simples `\`.

### Paso 2: Instalar el Paquete

Desde el directorio de tu proyecto Laravel:

```bash
composer require angelitosystems/filament-tenancy:@dev
```

Esto instalará el paquete desde la ruta local y creará un symlink. Verás un mensaje como:
```
- Installing angelitosystems/filament-tenancy (dev-main): Symlinking from C:/Proyectos/Angelito Systems/packages/filament-tenancy
```

### Paso 3: Verificar que el Comando Está Disponible

**En Windows PowerShell:**
```powershell
php artisan list | Select-String filament-tenancy
```

**En Windows CMD o Git Bash:**
```bash
php artisan list | findstr filament-tenancy
```

Deberías ver el comando `filament-tenancy:install` en la lista.

### Paso 4: Ejecutar el Comando de Instalación

```bash
php artisan filament-tenancy:install
```

### Ejemplo Completo de composer.json

Aquí tienes un ejemplo completo de cómo debería verse tu `composer.json`:

```json
{
    "name": "laravel/laravel",
    "type": "project",
    "repositories": [
        {
            "type": "path",
            "url": "C:/Proyectos/Angelito Systems/packages/filament-tenancy"
        }
    ],
    "require": {
        "php": "^8.1",
        "laravel/framework": "^11.0",
        "angelitosystems/filament-tenancy": "@dev"
    }
}
```

## Opción Alternativa: Publicar a Packagist o Repositorio Privado

Si quieres probarlo como si fuera una instalación real:

### Paso 1: Crear Tag de Versión

```bash
git tag v1.0.0
git push origin v1.0.0
```

### Paso 2: En tu Proyecto Laravel

```bash
composer require angelitosystems/filament-tenancy
```

### Paso 3: Ejecutar el Comando

```bash
php artisan filament-tenancy:install
```

## Verificación Post-Instalación

Después de ejecutar el comando, verifica:

1. **Archivo de Configuración:**
   
   **En Windows PowerShell:**
   ```powershell
   Test-Path config/tenancy.php
   Test-Path config/filament-tenancy.php
   ```
   
   **En Windows CMD:**
   ```cmd
   dir config\tenancy.php
   dir config\filament-tenancy.php
   ```

2. **ServiceProvider Registrado:**
   - Laravel 10: Verifica `config/app.php` en el array `providers`
   - Laravel 11: Verifica `bootstrap/providers.php`

3. **Migraciones Ejecutadas:**
   ```bash
   php artisan migrate:status
   ```
   Deberías ver las migraciones del paquete ejecutadas.

4. **Tabla de Tenants:**
   ```bash
   php artisan tinker
   >>> \DB::table('tenants')->count()
   ```

## Solución de Problemas

### El comando no aparece

1. Limpia la caché de configuración:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. Regenera el autoloader:
   ```bash
   composer dump-autoload
   ```

### Error al publicar configuración

Asegúrate de que el tag `filament-tenancy-config` esté correctamente configurado en `TenancyServiceProvider`.

### Error al registrar ServiceProvider

- Verifica que tengas permisos de escritura en `config/app.php` o `bootstrap/providers.php`
- Revisa que el formato del archivo sea correcto

## Probar Escenarios Específicos

### Probar sin ejecutar migraciones

```bash
php artisan filament-tenancy:install
# Responde "no" cuando pregunte por las migraciones
```

### Probar cuando el ServiceProvider ya está registrado

Ejecuta el comando dos veces para verificar que detecta correctamente el registro existente.

### Probar en Laravel 11

Crea un proyecto Laravel 11 fresco y sigue los pasos del Método Recomendado (Path Repository).

## Comandos Rápidos para Windows

Si estás en Windows PowerShell, aquí tienes una secuencia rápida de comandos:

```powershell
# 1. Navegar a tu proyecto Laravel
cd C:\Proyectos\MiProyectoLaravel

# 2. Agregar el repositorio (edita composer.json manualmente primero)

# 3. Instalar el paquete
composer require angelitosystems/filament-tenancy:@dev

# 4. Verificar el comando
php artisan list | Select-String filament-tenancy

# 5. Ejecutar la instalación
php artisan filament-tenancy:install
```

## Notas Importantes

- El comando es **idempotente**: puedes ejecutarlo múltiples veces sin problemas
- No sobrescribe archivos existentes sin confirmación
- Funciona tanto en Laravel 10 como Laravel 11

