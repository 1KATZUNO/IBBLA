<?php

namespace App\Services\Reportes;

use InvalidArgumentException;

/**
 * Periodo de reporte que no se puede usar (inicio despues del fin, mas de 24 meses, mes fuera de
 * 1-12...). El mensaje va en espanol y se puede mostrar tal cual al usuario.
 */
class PeriodoInvalido extends InvalidArgumentException
{
}
