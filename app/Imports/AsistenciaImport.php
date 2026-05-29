<?php

namespace App\Imports;

use App\Exports\PlantillaAsistenciaExport;
use App\Models\Asistencia;
use App\Models\AsistenciaClaseDetalle;
use App\Models\AsistenciaRegistroExtra;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Models\RegistroExtraTipo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Importa filas de asistencia desde una plantilla Excel generada por
 * `PlantillaAsistenciaExport`. Cada fila crea o actualiza un Culto + una
 * Asistencia, todo marcado como `cargado_retroactivo = true`.
 *
 * El Excel se valida fila por fila y se devuelve un reporte con
 * filasCreadas / filasActualizadas / errores para mostrar al usuario.
 *
 * Se utiliza ToCollection (no ToModel) para poder dar un preview antes de
 * persistir y manejar transacciones manualmente.
 */
class AsistenciaImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable;

    public array $creadas = [];

    public array $actualizadas = [];

    public array $errores = [];

    public array $preview = [];

    public bool $dryRun = false;

    /** Cache para evitar lookups repetidos */
    protected ?ClaseAsistencia $claseHistorica = null;

    protected ?RegistroExtraTipo $tipoTransmision = null;

    protected ?RegistroExtraTipo $tipoVehiculos = null;

    public function __construct(bool $dryRun = false)
    {
        $this->dryRun = $dryRun;
    }

    public function startRow(): int
    {
        // Fila 1: headers (la mapea WithHeadingRow). Fila 2: ejemplo. Datos desde fila 3.
        return 2;
    }

    public function collection($rows)
    {
        $tenantId = Auth::user()->tenant_id ?? null;
        $userId = Auth::id();

        if (! $tenantId) {
            $this->errores[] = [
                'fila' => 0,
                'mensaje' => 'No se pudo determinar el tenant del usuario actual.',
            ];

            return;
        }

        $rowNum = $this->startRow();
        foreach ($rows as $row) {
            // Saltar fila de ejemplo (segunda) y filas completamente vacías
            $rowNum++;
            $datos = $this->mapearFila($row);

            // Ignorar fila de ejemplo (es la primera fila tras el header)
            if ($rowNum === 3 && $this->esFilaEjemplo($datos)) {
                continue;
            }

            // Saltar filas vacías
            if (! $datos['fecha'] && ! $datos['tipo_culto']) {
                continue;
            }

            // Validar
            $error = $this->validarFila($datos, $rowNum);
            if ($error) {
                $this->errores[] = $error;

                continue;
            }

            if ($this->dryRun) {
                $this->preview[] = [
                    'fila' => $rowNum,
                    'fecha' => $datos['fecha'],
                    'tipo_culto' => $datos['tipo_culto'],
                    'total_estimado' => $this->totalEstimado($datos),
                ];

                continue;
            }

            // Persistir
            DB::transaction(function () use ($datos, $tenantId, $userId, $rowNum) {
                $this->persistirAsistencia($datos, $tenantId, $userId, $rowNum);
            });
        }
    }

    protected function mapearFila($row): array
    {
        $arr = $row->toArray();
        $get = function ($key) use ($arr) {
            // Maatwebsite normaliza heading: "Capilla Adultos H" → "capilla_adultos_h"
            // Buscamos por las claves de COLUMNAS slugificando el label.
            return null;
        };

        // Slug helper: misma transformación que aplica Maatwebsite a headings
        $slug = function (string $label): string {
            $s = mb_strtolower($label, 'UTF-8');
            $s = preg_replace('/[áàâãä]/u', 'a', $s);
            $s = preg_replace('/[éèêë]/u', 'e', $s);
            $s = preg_replace('/[íìîï]/u', 'i', $s);
            $s = preg_replace('/[óòôõö]/u', 'o', $s);
            $s = preg_replace('/[úùûü]/u', 'u', $s);
            $s = preg_replace('/[ñ]/u', 'n', $s);
            $s = preg_replace('/[^a-z0-9]+/u', '_', $s);
            $s = trim($s, '_');

            return $s;
        };

        $datos = [];
        foreach (PlantillaAsistenciaExport::COLUMNAS as $key => $col) {
            $headingSlug = $slug($col['label']);
            $datos[$key] = $arr[$headingSlug] ?? null;
        }

        // Normalizar fecha
        if ($datos['fecha']) {
            try {
                if (is_numeric($datos['fecha'])) {
                    // Excel serial date
                    $datos['fecha'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($datos['fecha'])->format('Y-m-d');
                } else {
                    $datos['fecha'] = Carbon::parse($datos['fecha'])->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Dejar como está; validación reportará el error
            }
        }

        if ($datos['tipo_culto']) {
            $datos['tipo_culto'] = strtolower(trim((string) $datos['tipo_culto']));
        }

        return $datos;
    }

    protected function esFilaEjemplo(array $datos): bool
    {
        // La fila de ejemplo tiene fecha 2026-01-04 + capilla H=25 → improbable repetir exactamente
        return $datos['fecha'] === '2026-01-04' && (int) $datos['chapel_adultos_hombres'] === 25;
    }

    protected function validarFila(array $datos, int $rowNum): ?array
    {
        // Fecha
        if (! $datos['fecha']) {
            return ['fila' => $rowNum, 'mensaje' => 'Falta fecha del culto.'];
        }
        try {
            $fecha = Carbon::parse($datos['fecha']);
        } catch (\Throwable $e) {
            return ['fila' => $rowNum, 'mensaje' => "Fecha inválida: '{$datos['fecha']}'."];
        }

        // tipo_culto
        $tiposValidos = ['domingo', 'domingo_am', 'domingo_pm', 'miercoles', 'sabado', 'especial'];
        if (! in_array($datos['tipo_culto'], $tiposValidos)) {
            return [
                'fila' => $rowNum,
                'mensaje' => "Tipo culto inválido: '{$datos['tipo_culto']}'. Valores aceptados: ".implode(', ', $tiposValidos),
            ];
        }

        // Números no negativos
        foreach (PlantillaAsistenciaExport::COLUMNAS as $key => $col) {
            if (in_array($key, ['fecha', 'tipo_culto'])) {
                continue;
            }
            $val = $datos[$key];
            if ($val !== null && $val !== '' && (! is_numeric($val) || $val < 0)) {
                return [
                    'fila' => $rowNum,
                    'mensaje' => "Valor inválido en '{$col['label']}': '{$val}' (debe ser un número >= 0).",
                ];
            }
        }

        return null;
    }

    protected function totalEstimado(array $datos): int
    {
        $intval = fn ($key) => (int) ($datos[$key] ?? 0);

        return $intval('chapel_adultos_hombres') + $intval('chapel_adultos_mujeres')
            + $intval('ninos_total') + $intval('transmision');
    }

    protected function persistirAsistencia(array $datos, int $tenantId, ?int $userId, int $rowNum): void
    {
        $intval = fn ($key) => (int) ($datos[$key] ?? 0);

        // 1) Culto
        $culto = Culto::firstOrCreate(
            [
                'fecha' => $datos['fecha'],
                'tipo_culto' => $datos['tipo_culto'],
                'tenant_id' => $tenantId,
            ],
            ['cerrado' => true, 'cerrado_at' => now(), 'cerrado_por' => $userId]
        );

        // 2) Asistencia
        $asistencia = Asistencia::updateOrCreate(
            [
                'culto_id' => $culto->id,
                'tenant_id' => $tenantId,
            ],
            [
                'chapel_adultos_hombres' => $intval('chapel_adultos_hombres'),
                'chapel_adultos_mujeres' => $intval('chapel_adultos_mujeres'),
                'salvos_adulto_hombre' => $intval('salvos_adulto_hombre'),
                'salvos_adulto_mujer' => $intval('salvos_adulto_mujer'),
                'salvos_joven_hombre' => $intval('salvos_joven_hombre'),
                'salvos_joven_mujer' => $intval('salvos_joven_mujer'),
                'salvos_nino' => $intval('salvos_nino'),
                'salvos_nina' => $intval('salvos_nina'),
                'bautismos_adulto_hombre' => $intval('bautismos_adulto_hombre'),
                'bautismos_adulto_mujer' => $intval('bautismos_adulto_mujer'),
                'bautismos_joven_hombre' => $intval('bautismos_joven_hombre'),
                'bautismos_joven_mujer' => $intval('bautismos_joven_mujer'),
                'bautismos_nino' => $intval('bautismos_nino'),
                'bautismos_nina' => $intval('bautismos_nina'),
                'visitas_adulto_hombre' => $intval('visitas_adulto_hombre'),
                'visitas_adulto_mujer' => $intval('visitas_adulto_mujer'),
                'visitas_joven_hombre' => $intval('visitas_joven_hombre'),
                'visitas_joven_mujer' => $intval('visitas_joven_mujer'),
                'visitas_nino' => $intval('visitas_nino'),
                'visitas_nina' => $intval('visitas_nina'),
                'cargado_retroactivo' => true,
                'cargado_retroactivo_at' => now(),
                'cargado_retroactivo_por' => $userId,
            ]
        );

        // 3) Niños agregados → clase "Histórico" 50/50
        $ninosTotal = $intval('ninos_total');
        if ($ninosTotal > 0) {
            $clase = $this->getClaseHistorica($tenantId);
            $hombres = intdiv($ninosTotal, 2) + ($ninosTotal % 2);
            $mujeres = intdiv($ninosTotal, 2);
            AsistenciaClaseDetalle::updateOrCreate(
                [
                    'asistencia_id' => $asistencia->id,
                    'clase_asistencia_id' => $clase->id,
                ],
                [
                    'hombres' => $hombres,
                    'mujeres' => $mujeres,
                    'maestros_hombres' => 0,
                    'maestros_mujeres' => 0,
                ]
            );
        }

        // 4) Transmisión
        $transmision = $intval('transmision');
        if ($transmision > 0) {
            $tipo = $this->getTipoTransmision($tenantId);
            if ($tipo) {
                AsistenciaRegistroExtra::updateOrCreate(
                    [
                        'asistencia_id' => $asistencia->id,
                        'registro_extra_tipo_id' => $tipo->id,
                    ],
                    ['valores' => ['miembros' => $transmision]]
                );
            }
        }

        // 5) Vehículos
        $autos = $intval('vehiculos_autos');
        $motos = $intval('vehiculos_motos');
        if ($autos > 0 || $motos > 0) {
            $tipo = $this->getTipoVehiculos($tenantId);
            if ($tipo) {
                AsistenciaRegistroExtra::updateOrCreate(
                    [
                        'asistencia_id' => $asistencia->id,
                        'registro_extra_tipo_id' => $tipo->id,
                    ],
                    ['valores' => ['autos' => $autos, 'motos' => $motos]]
                );
            }
        }

        // 6) Recalcular total
        $asistencia->load(['detallesClases', 'registrosExtra.tipo']);
        $asistencia->update([
            'total_asistencia' => $asistencia->getTotalCapilla()
                + $asistencia->getTotalClases()
                + $asistencia->getTotalRegistrosExtraAsistencia(),
        ]);

        $key = $asistencia->wasRecentlyCreated ? 'creadas' : 'actualizadas';
        $this->{$key}[] = [
            'fila' => $rowNum,
            'culto_id' => $culto->id,
            'asistencia_id' => $asistencia->id,
            'fecha' => $datos['fecha'],
            'tipo_culto' => $datos['tipo_culto'],
            'total' => $asistencia->total_asistencia,
        ];
    }

    protected function getClaseHistorica(int $tenantId): ClaseAsistencia
    {
        if ($this->claseHistorica) {
            return $this->claseHistorica;
        }
        $this->claseHistorica = ClaseAsistencia::firstOrCreate(
            ['slug' => 'historico', 'tenant_id' => $tenantId],
            [
                'nombre' => 'Niños (Histórico)',
                'orden' => 999,
                'activa' => true,
                'tiene_maestros' => false,
                'color' => '#9CA3AF',
            ]
        );

        return $this->claseHistorica;
    }

    protected function getTipoTransmision(int $tenantId): ?RegistroExtraTipo
    {
        if ($this->tipoTransmision) {
            return $this->tipoTransmision;
        }
        $this->tipoTransmision = RegistroExtraTipo::where('tenant_id', $tenantId)->where('slug', 'transmision')->first();

        return $this->tipoTransmision;
    }

    protected function getTipoVehiculos(int $tenantId): ?RegistroExtraTipo
    {
        if ($this->tipoVehiculos) {
            return $this->tipoVehiculos;
        }
        $this->tipoVehiculos = RegistroExtraTipo::where('tenant_id', $tenantId)->where('slug', 'vehiculos')->first();

        return $this->tipoVehiculos;
    }
}
