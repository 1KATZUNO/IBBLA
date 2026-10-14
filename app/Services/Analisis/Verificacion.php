<?php

namespace App\Services\Analisis;

/** Una revisión del análisis de ingresos. Solo lee la base de datos y devuelve su sección del informe. */
interface Verificacion
{
    public function ejecutar(ContextoAnalisis $contexto): SeccionAnalisis;
}
