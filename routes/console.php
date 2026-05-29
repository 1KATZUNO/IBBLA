<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purgar auditoria cada mes (registros con mas de 30 dias)
Schedule::command('audit:purge')->monthly();

// Obtener tipo de cambio del BCCR diariamente a las 6:00 PM (Costa Rica)
Schedule::command('exchange:fetch')->dailyAt('18:00')->timezone('America/Costa_Rica');

// Reporte mensual automático: el día 1 de cada mes a las 8:00 AM Costa Rica,
// se envía un correo con los reportes del mes anterior (Fase 7).
// Destinatarios se leen de REPORTE_MENSUAL_TO en .env (lista separada por comas).
Schedule::command('reporte:enviar-mensual')
    ->monthlyOn(1, '08:00')
    ->timezone('America/Costa_Rica');
