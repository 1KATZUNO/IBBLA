<?php

namespace Database\Seeders;

use App\Models\Ministerio;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Siembra la lista de ministerios definida en config/ministerios.php
 * (ahí se edita; ahí también está la nota de que los 30 reales los entrega el cliente).
 *
 * Idempotente: firstOrCreate por (iglesia, nombre). Correrlo N veces deja lo mismo, y no
 * pisa lo que el administrador ya cambió desde la pantalla de Ministerios.
 *
 *     php artisan db:seed --class=MinisterioSeeder --force
 */
class MinisterioSeeder extends Seeder
{
    public function run(): void
    {
        $lista = $this->normalizar((array) config('ministerios.lista', []));
        $slugs = config('ministerios.tenant_slugs');

        // null = todas las iglesias; una lista = solo esas (una lista vacía no siembra ninguna).
        $iglesias = Tenant::query()
            ->when($slugs !== null, fn ($q) => $q->whereIn('slug', (array) $slugs))
            ->get();

        foreach ($iglesias as $iglesia) {
            $creados = 0;

            foreach ($lista as $m) {
                // Equivale a firstOrCreate por (iglesia, nombre), pero con firstOrNew + save
                // porque DatabaseSeeder usa WithoutModelEvents: bajo ese modo el hook
                // `creating` del modelo no corre, así que slug y orden se fijan aquí a mano.
                $ministerio = Ministerio::withoutGlobalScopes()->firstOrNew([
                    'tenant_id' => $iglesia->id,
                    'nombre' => $m['nombre'],
                ]);

                if ($ministerio->exists) {
                    continue;
                }

                $ministerio->fill([
                    'slug' => Ministerio::slugUnico($m['nombre'], $iglesia->id),
                    'color' => $m['color'],
                    'descripcion' => $m['descripcion'],
                    'activo' => true,
                    'orden' => $m['orden'],
                ])->save();
                $creados++;
            }

            $this->command?->info(sprintf(
                '%s: %d ministerio(s) creado(s), %d ya existían.',
                $iglesia->siglas ?: $iglesia->slug,
                $creados,
                count($lista) - $creados,
            ));
        }
    }

    /**
     * Acepta líneas "Nombre" o ["nombre" => ..., "descripcion" => ..., "color" => ...],
     * descarta vacías y repetidas, y completa color y orden.
     *
     * @return list<array{nombre: string, descripcion: ?string, color: string, orden: int}>
     */
    private function normalizar(array $lista): array
    {
        $paleta = array_values(array_filter((array) config('ministerios.paleta', []))) ?: ['#6B7280'];
        $vistos = [];
        $salida = [];

        foreach ($lista as $linea) {
            $datos = is_array($linea) ? $linea : ['nombre' => $linea];
            $nombre = trim((string) ($datos['nombre'] ?? ''));

            // Mismo criterio que la base (collation sin distinguir mayúsculas ni acentos
            // en MySQL): "alabanza" y "Alabanza" son el mismo ministerio.
            $clave = mb_strtolower($nombre);
            if ($nombre === '' || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $descripcion = mb_substr(trim((string) ($datos['descripcion'] ?? '')), 0, 255);

            $posicion = count($salida);
            $salida[] = [
                'nombre' => mb_substr($nombre, 0, 80),
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'color' => $datos['color'] ?? $paleta[$posicion % count($paleta)],
                'orden' => $posicion + 1,
            ];
        }

        return $salida;
    }
}
