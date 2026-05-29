<?php

namespace Database\Seeders;

use App\Models\Ministerio;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MinisterioSeeder extends Seeder
{
    /**
     * Siembra los 7 ministerios típicos para cada tenant existente.
     * Idempotente vía updateOrCreate por (tenant_id, slug).
     */
    public function run(): void
    {
        $ministerios = [
            ['nombre' => 'Alabanza', 'slug' => 'alabanza', 'color' => '#8B5CF6', 'descripcion' => 'Músicos y cantantes del ministerio de adoración.', 'orden' => 1],
            ['nombre' => 'Transmisión', 'slug' => 'transmision', 'color' => '#F59E0B', 'descripcion' => 'Sonido, video, streaming y técnicos.', 'orden' => 2],
            ['nombre' => 'Ujieres', 'slug' => 'ujieres', 'color' => '#10B981', 'descripcion' => 'Recepción, orden y atención al asistente.', 'orden' => 3],
            ['nombre' => 'Niños / Escuela Dominical', 'slug' => 'ninos', 'color' => '#EC4899', 'descripcion' => 'Maestros y ayudantes de clases por edad.', 'orden' => 4],
            ['nombre' => 'Diaconado', 'slug' => 'diaconado', 'color' => '#3B82F6', 'descripcion' => 'Diáconos y diaconisas.', 'orden' => 5],
            ['nombre' => 'Misiones', 'slug' => 'misiones', 'color' => '#EF4444', 'descripcion' => 'Comité y equipo de misiones.', 'orden' => 6],
            ['nombre' => 'Limpieza / Mantenimiento', 'slug' => 'mantenimiento', 'color' => '#6B7280', 'descripcion' => 'Equipo de limpieza y mantenimiento.', 'orden' => 7],
        ];

        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            foreach ($ministerios as $m) {
                Ministerio::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $m['slug']],
                    array_merge($m, ['activo' => true]),
                );
            }
        }
    }
}
