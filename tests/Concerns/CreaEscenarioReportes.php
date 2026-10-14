<?php

namespace Tests\Concerns;

use App\Models\Persona;
use App\Models\Promesa;
use App\Models\TipoCambio;

/**
 * Tres personas con todo lo que complica los reportes de dinero (2026, dólar de hoy a ₡500):
 *
 * Ana Mora — promesas: misiones ₡10.000 mensual, construcción ₡2.000 semanal, seminario $5 quincenal.
 *   04/01 CRC  diezmo 30.000 + misiones 10.000,50
 *   01/02 CRC  misiones 10.000 + construcción 2.000 + campa 3.000 (campa sin promesa)
 *   08/02 USD  misiones $20 con tipo de cambio ₡510 del sobre = ₡10.200
 *   04/03 USD  seminario $7,25 sin tipo de cambio = ₡3.625 (al de hoy)
 *   07/06 CRC  misiones 15.000
 *   04/10 CRC  diezmo 40.000 + construcción 8.000,75
 *   fuera:     28/12/2025 (otro año) y 01/11/2026 (después de los periodos de prueba)
 *
 * Beto Rojas — sin promesa.
 *   15/02 CRC  misiones 5.000 + diezmo 20.000
 *   02/08 CRC  préstamo 1.234,56
 *
 * Carla Solano — promesas: campa ₡2.000 y después campa ₡3.000 (vale la última), diezmo ₡50.000
 *   (categoría excluida de promesas: no cuenta), micro ₡1.500 quincenal.
 *   11/01 CRC  campa 3.000 + micro 3.000
 *   08/03 CRC  campa 6.000,10 + pro_templo 500 (categoría que la iglesia ya no tiene)
 *   06/09 CRC  micro 1.000
 *
 * Además: un sobre anónimo, una persona inactiva con promesa y una persona de otra iglesia.
 */
trait CreaEscenarioReportes
{
    use CreaEscenarioWhatsApp;

    protected Persona $ana;

    protected Persona $beto;

    protected Persona $carla;

    protected function crearEscenarioReportes(): void
    {
        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
        TipoCambio::create(['fecha' => '2026-01-01', 'compra' => 495, 'venta' => 500, 'source' => 'prueba']);
        app()->instance('current_tenant', $this->iglesia);

        $this->ana = $this->crearPersona('Ana Mora');
        $this->beto = $this->crearPersona('Beto Rojas', '7777-6666');
        $this->carla = $this->crearPersona('Carla Solano', '6666-5555');

        $this->crearSobre($this->crearCulto('2026-01-04'), $this->ana, ['diezmo' => 30000, 'misiones' => 10000.50]);
        $this->crearSobre($this->crearCulto('2026-02-01'), $this->ana, ['misiones' => 10000, 'construccion' => 2000, 'campa' => 3000]);
        $this->crearSobre($this->crearCulto('2026-02-08'), $this->ana, ['misiones' => 20], 'USD', 510);
        $this->crearSobre($this->crearCulto('2026-03-04', 'miércoles'), $this->ana, ['seminario' => 7.25], 'USD', null);
        $this->crearSobre($this->crearCulto('2026-06-07'), $this->ana, ['misiones' => 15000]);
        $this->crearSobre($this->crearCulto('2026-10-04'), $this->ana, ['diezmo' => 40000, 'construccion' => 8000.75]);
        $this->crearSobre($this->crearCulto('2025-12-28'), $this->ana, ['diezmo' => 99999]);
        $this->crearSobre($this->crearCulto('2026-11-01'), $this->ana, ['misiones' => 99999]);
        $this->promesa($this->ana, 'misiones', 10000, 'mensual');
        $this->promesa($this->ana, 'construccion', 2000, 'semanal');
        $this->promesa($this->ana, 'seminario', 5, 'quincenal', 'USD');

        $this->crearSobre($this->crearCulto('2026-02-15'), $this->beto, ['misiones' => 5000, 'diezmo' => 20000]);
        $this->crearSobre($this->crearCulto('2026-08-02'), $this->beto, ['prestamo' => 1234.56]);

        $this->promesa($this->carla, 'campa', 2000, 'mensual');
        $this->promesa($this->carla, 'campa', 3000, 'mensual');
        $this->promesa($this->carla, 'diezmo', 50000, 'mensual');
        $this->promesa($this->carla, 'micro', 1500, 'quincenal');
        $this->crearSobre($this->crearCulto('2026-01-11'), $this->carla, ['campa' => 3000, 'micro' => 3000]);
        $this->crearSobre($this->crearCulto('2026-03-08'), $this->carla, ['campa' => 6000.10, 'pro_templo' => 500]);
        $this->crearSobre($this->crearCulto('2026-09-06'), $this->carla, ['micro' => 1000]);

        // Lo que no debe aparecer en ningún reporte de estas tres personas.
        $this->crearSobre($this->crearCulto('2026-01-18'), null, ['misiones' => 7000]);
        $inactiva = Persona::create(['nombre' => 'Diego Inactivo', 'activo' => false, 'tenant_id' => $this->iglesia->id]);
        $this->promesa($inactiva, 'misiones', 5000, 'mensual');
        $otra = $this->crearOtraIglesia();
        $ajena = $this->crearPersona('Zoe Ajena', '5555-4444', $otra->id);
        $this->promesa($ajena, 'misiones', 1000, 'mensual');
        $this->crearSobre($this->crearCulto('2026-01-11', 'domingo', $otra->id), $ajena, ['misiones' => 1000]);
    }

    protected function promesa(Persona $persona, string $categoria, float $monto, string $frecuencia, string $moneda = 'CRC'): Promesa
    {
        return Promesa::create([
            'persona_id' => $persona->id,
            'categoria' => $categoria,
            'monto' => $monto,
            'moneda' => $moneda,
            'frecuencia' => $frecuencia,
        ]);
    }
}
