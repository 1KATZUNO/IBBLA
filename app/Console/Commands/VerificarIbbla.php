<?php

namespace App\Console\Commands;

use App\Models\Persona;
use App\Models\Tenant;
use App\Services\InformePersonaService;
use App\Services\ReporteGeneralPdf;
use App\Services\ReporteGeneralService;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Verificación posterior al deploy (y antes de culpar al código cuando algo no funciona en el servidor).
 *
 * Uso:
 *   php artisan ibbla:verificar
 *   php artisan ibbla:verificar --tenant=7          # iglesia a usar para medir los reportes
 *   php artisan ibbla:verificar --sin-reportes      # solo entorno, BD, migraciones y permisos
 *   php artisan ibbla:verificar --umbral=10         # segundos a partir de los cuales un reporte es FALLA
 *
 * Cada chequeo termina en OK, AVISO o FALLA. El comando devuelve 1 si hay al menos una FALLA
 * (los AVISO no cambian el código de salida).
 *
 * No escribe en la base de datos ni cambia configuración: los PDF se generan en memoria y se descartan.
 */
class VerificarIbbla extends Command
{
    protected $signature = 'ibbla:verificar
        {--tenant= : Id de la iglesia para medir los reportes (por defecto, la que tiene mas personas activas)}
        {--sin-reportes : No generar los reportes pesados}
        {--umbral=15 : Segundos a partir de los cuales un reporte cuenta como FALLA (la mitad es AVISO)}';

    protected $description = 'Verifica entorno, base de datos, migraciones, permisos y tiempos de los reportes pesados.';

    private int $fallas = 0;

    private int $avisos = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Verificación de IBBLA</>  ('.config('app.url').')');

        $this->seccion('Entorno');
        $this->chequearEntorno();

        $this->seccion('Base de datos');
        $bdOk = $this->chequearConexion();
        if ($bdOk) {
            $this->chequearMigraciones();
            $this->chequearCuentasDeSemilla();
        } else {
            $this->falla('Migraciones', 'no se pueden revisar sin conexión a la base de datos');
        }

        $this->seccion('Permisos de escritura');
        $this->chequearPermisos();

        $this->seccion('Reportes pesados');
        if ($this->option('sin-reportes')) {
            $this->aviso('Reportes', 'omitidos (--sin-reportes)');
        } elseif (! $bdOk) {
            $this->falla('Reportes', 'no se pueden medir sin conexión a la base de datos');
        } else {
            $this->chequearReportes();
        }

        $this->newLine();
        if ($this->fallas > 0) {
            $this->line("<fg=red;options=bold>RESUMEN: FALLA</>  ({$this->fallas} falla(s), {$this->avisos} aviso(s))");

            return self::FAILURE;
        }
        $this->line("<fg=green;options=bold>RESUMEN: OK</>  ({$this->avisos} aviso(s))");

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ entorno

    private function chequearEntorno(): void
    {
        $produccion = app()->environment('production');
        $env = app()->environment();

        if ($produccion) {
            $this->ok('APP_ENV', $env);
        } else {
            $this->aviso('APP_ENV', "{$env} (en el servidor debe ser production)");
        }

        if (! config('app.debug')) {
            $this->ok('APP_DEBUG', 'false');
        } elseif ($produccion) {
            $this->falla('APP_DEBUG', 'true en producción: mostraría rutas, consultas y variables ante cualquier error');
        } else {
            $this->aviso('APP_DEBUG', 'true (en el servidor debe ser false)');
        }

        if (config('app.key')) {
            $this->ok('APP_KEY', 'definida');
        } else {
            $this->falla('APP_KEY', 'vacía: las sesiones y los enlaces firmados no funcionan');
        }

        $caches = [
            'Caché de configuración (config:cache)' => app()->configurationIsCached(),
            'Caché de rutas (route:cache)' => app()->routesAreCached(),
            'Caché de vistas (view:cache)' => $this->hayVistasCacheadas(),
        ];
        foreach ($caches as $nombre => $activa) {
            if ($activa) {
                $this->ok($nombre, 'activa');
            } elseif ($produccion) {
                $this->falla($nombre, 'inactiva: correr deploy/hostinger.sh o los comandos de caché');
            } else {
                $this->aviso($nombre, 'inactiva (normal en desarrollo)');
            }
        }

        $this->ok('PHP', PHP_VERSION);
    }

    private function hayVistasCacheadas(): bool
    {
        $ruta = config('view.compiled');

        return is_string($ruta) && is_dir($ruta) && count(glob($ruta.DIRECTORY_SEPARATOR.'*.php') ?: []) > 0;
    }

    // ------------------------------------------------------------------ base de datos

    private function chequearConexion(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $this->ok('Conexión', DB::connection()->getDriverName().' / '.DB::connection()->getDatabaseName());

            return true;
        } catch (Throwable $e) {
            $this->falla('Conexión', 'no conecta: '.$this->primeraLinea($e->getMessage()));

            return false;
        }
    }

    private function chequearMigraciones(): void
    {
        try {
            $migrator = app('migrator');
            $repositorio = $migrator->getRepository();

            if (! $repositorio->repositoryExists()) {
                $this->falla('Migraciones', 'la tabla migrations no existe: falta correr php artisan migrate --force');

                return;
            }

            $archivos = array_keys($migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')])));
            $pendientes = array_values(array_diff($archivos, $repositorio->getRan()));

            if ($pendientes === []) {
                $this->ok('Migraciones', 'al día ('.count($archivos).')');

                return;
            }

            $this->falla('Migraciones', count($pendientes).' pendiente(s): '.implode(', ', array_slice($pendientes, 0, 3)).(count($pendientes) > 3 ? ', ...' : ''));
        } catch (Throwable $e) {
            $this->falla('Migraciones', 'no se pudieron revisar: '.$this->primeraLinea($e->getMessage()));
        }
    }

    /**
     * AdminUserSeeder crea usuarios con contraseñas escritas en el repositorio. Si siguen vivas
     * en el servidor, cualquiera que lea el codigo puede entrar.
     */
    private function chequearCuentasDeSemilla(): void
    {
        $semilla = [
            'super@admin.com' => 'admin123',
            'admin@ibbsc.com' => 'admin123',
            'tesorero@ibbsc.com' => 'tesorero123',
        ];

        try {
            $expuestas = [];
            foreach (User::withoutGlobalScopes()->whereIn('email', array_keys($semilla))->orderBy('email')->get() as $usuario) {
                if (Hash::check($semilla[$usuario->email], $usuario->password)) {
                    $expuestas[] = $usuario->email;
                }
            }
        } catch (Throwable $e) {
            $this->aviso('Cuentas de semilla', 'no se pudieron revisar: '.$this->primeraLinea($e->getMessage()));

            return;
        }

        if ($expuestas === []) {
            $this->ok('Cuentas de semilla', 'ninguna conserva la contraseña del repositorio');
        } elseif (app()->environment('production')) {
            $this->falla('Cuentas de semilla', 'contraseña publica en el repositorio: '.implode(', ', $expuestas).' (cambiarla o borrar la cuenta)');
        } else {
            $this->aviso('Cuentas de semilla', implode(', ', $expuestas).' conservan la contraseña de AdminUserSeeder (cambiarla antes de produccion)');
        }
    }

    // ------------------------------------------------------------------ permisos

    private function chequearPermisos(): void
    {
        $rutas = [
            'storage' => storage_path(),
            'storage/logs' => storage_path('logs'),
            'storage/app' => storage_path('app'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        foreach ($rutas as $nombre => $ruta) {
            if (! is_dir($ruta)) {
                $this->falla($nombre, 'no existe');
            } elseif (! is_writable($ruta)) {
                $this->falla($nombre, 'sin permiso de escritura (chmod 775)');
            } else {
                $this->ok($nombre, 'escribible');
            }
        }

        if (file_exists(public_path('storage'))) {
            $this->ok('public/storage', 'enlace presente');
        } else {
            $this->aviso('public/storage', 'falta el enlace: php artisan storage:link (logos e imágenes subidas no se verían)');
        }
    }

    // ------------------------------------------------------------------ reportes

    private function chequearReportes(): void
    {
        $iglesia = $this->elegirIglesia();
        if (! $iglesia) {
            $this->aviso('Reportes', 'no hay iglesia con personas activas: nada que medir');

            return;
        }

        app()->instance('current_tenant', $iglesia);
        $activas = Persona::where('tenant_id', $iglesia->id)->where('activo', true)->count();
        $this->ok('Iglesia usada', "{$iglesia->siglas} (id {$iglesia->id}, {$activas} personas activas)");

        if (Tenant::count() > 1) {
            $this->aviso('Varias iglesias', 'en consola las consultas no filtran por iglesia: el tiempo incluye los datos de todas (cota superior)');
        }

        $umbral = max(1.0, (float) $this->option('umbral'));
        $hoy = now();

        $this->medir('Reporte General (PDF)', $umbral, function () use ($hoy) {
            $reporte = app(ReporteGeneralService::class)->generar($hoy->year, $hoy->month);
            $pdf = app(ReporteGeneralPdf::class)->render($reporte, $hoy->year, $hoy->month, tenant_pdf_data(), $hoy);

            return $this->tamanoPdf($pdf);
        });

        $persona = $this->elegirPersona($iglesia);
        if (! $persona) {
            $this->aviso('Estado de cuenta (PDF)', 'sin personas con sobres o promesas');

            return;
        }

        $this->medir("Estado de cuenta de {$persona->nombre} (PDF)", $umbral, function () use ($persona) {
            return $this->tamanoPdf(app(InformePersonaService::class)->pdf($persona)->output());
        });
    }

    /** La iglesia pedida, o la que tiene más personas activas. */
    private function elegirIglesia(): ?Tenant
    {
        if ($this->option('tenant')) {
            return Tenant::find((int) $this->option('tenant'));
        }

        $id = Persona::query()
            ->where('activo', true)
            ->select('tenant_id', DB::raw('count(*) as total'))
            ->groupBy('tenant_id')
            ->orderByDesc('total')
            ->value('tenant_id');

        return $id ? Tenant::find($id) : null;
    }

    /** La persona con más sobres (el estado de cuenta más pesado que se puede pedir hoy); si nadie tiene, la primera. */
    private function elegirPersona(Tenant $iglesia): ?Persona
    {
        $id = DB::table('sobres')
            ->join('personas', 'personas.id', '=', 'sobres.persona_id')
            ->where('personas.tenant_id', $iglesia->id)
            ->select('personas.id', DB::raw('count(*) as total'))
            ->groupBy('personas.id')
            ->orderByDesc('total')
            ->value('id');

        return $id
            ? Persona::find($id)
            : Persona::where('tenant_id', $iglesia->id)->where('activo', true)->orderBy('id')->first();
    }

    private function medir(string $nombre, float $umbral, callable $generar): void
    {
        $inicio = microtime(true);
        try {
            $detalle = $generar();
        } catch (Throwable $e) {
            $this->falla($nombre, 'error al generar: '.$this->primeraLinea($e->getMessage()));

            return;
        }
        $segundos = microtime(true) - $inicio;
        $texto = sprintf('%.1f s, %s (límite %d s)', $segundos, $detalle, (int) $umbral);

        if ($segundos >= $umbral) {
            $this->falla($nombre, $texto);
        } elseif ($segundos >= $umbral / 2) {
            $this->aviso($nombre, $texto);
        } else {
            $this->ok($nombre, $texto);
        }
    }

    private function tamanoPdf(string $contenido): string
    {
        return round(strlen($contenido) / 1024).' KB';
    }

    // ------------------------------------------------------------------ salida

    private function seccion(string $titulo): void
    {
        $this->newLine();
        $this->line("<options=bold>{$titulo}</>");
    }

    private function ok(string $nombre, string $detalle): void
    {
        $this->line("  <fg=green>[OK]</>     {$nombre}: {$detalle}");
    }

    private function aviso(string $nombre, string $detalle): void
    {
        $this->avisos++;
        $this->line("  <fg=yellow>[AVISO]</>  {$nombre}: {$detalle}");
    }

    private function falla(string $nombre, string $detalle): void
    {
        $this->fallas++;
        $this->line("  <fg=red>[FALLA]</>   {$nombre}: {$detalle}");
    }

    private function primeraLinea(string $mensaje): string
    {
        return trim(strtok($mensaje, "\n") ?: $mensaje);
    }
}
