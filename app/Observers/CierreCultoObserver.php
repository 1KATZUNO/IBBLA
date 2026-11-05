<?php

namespace App\Observers;

use App\Models\Culto;
use App\Services\Reportes\SincronizadorCompromisos;

/**
 * La tabla compromisos sigue al culto cerrado (regla 2.4 de 04-DECISIONES.md): se sincroniza su mes
 * al cerrarlo y tambien al borrarlo (QE-006), sin que ninguna pantalla escriba al consultar. Un
 * error al sincronizar no impide la accion (queda en el log; se arregla con el comando o el boton).
 * Las correcciones de sobres en un culto cerrado las sincroniza RecuentoController::despuesDeCambiar.
 */
class CierreCultoObserver
{
    public function updated(Culto $culto): void
    {
        if (! $culto->wasChanged('cerrado') || ! $culto->cerrado) {
            return;
        }

        app(SincronizadorCompromisos::class)->sincronizarMesDeCulto($culto);
    }

    /** Borrar un culto cerrado saca sus sobres del mes: el Dashboard de promesas deja de contar ese dinero. */
    public function deleted(Culto $culto): void
    {
        if (! $culto->cerrado) {
            return;
        }

        app(SincronizadorCompromisos::class)->sincronizarMesDeCulto($culto);
    }
}
