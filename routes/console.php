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
