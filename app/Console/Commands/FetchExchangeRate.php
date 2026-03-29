<?php

namespace App\Console\Commands;

use App\Models\TipoCambio;
use App\Services\BccrExchangeRateService;
use Illuminate\Console\Command;

class FetchExchangeRate extends Command
{
    protected $signature = 'exchange:fetch
                            {fecha? : Fecha específica (YYYY-MM-DD). Si no se indica, usa hoy.}
                            {--manual : Permite ingresar tipo de cambio manualmente}';

    protected $description = 'Obtiene el tipo de cambio USD/CRC del Banco Central de Costa Rica';

    public function handle(BccrExchangeRateService $service): int
    {
        if ($this->option('manual')) {
            return $this->ingresarManual();
        }

        $fecha = $this->argument('fecha') ?? now()->toDateString();

        if (! $service->credencialesConfiguradas()) {
            $this->error('Token BCCR no configurado. Agregue BCCR_TOKEN en .env (obtenerlo en https://sdd.bccr.fi.cr)');
            $this->info('Puede usar --manual para ingresar el tipo de cambio manualmente.');

            return self::FAILURE;
        }

        $this->info("Obteniendo tipo de cambio para {$fecha}...");

        $tipoCambio = $service->obtenerPorFecha($fecha);

        if (! $tipoCambio) {
            $this->error('No se pudo obtener el tipo de cambio del BCCR.');
            $this->info('Puede usar --manual para ingresar el tipo de cambio manualmente.');

            return self::FAILURE;
        }

        $this->info("Tipo de cambio ({$tipoCambio->fecha->format('d/m/Y')}):");
        $this->table(
            ['', 'Valor'],
            [
                ['Compra', '₡ '.number_format((float) $tipoCambio->compra, 2)],
                ['Venta', '₡ '.number_format((float) $tipoCambio->venta, 2)],
                ['Fuente', $tipoCambio->source],
            ]
        );

        return self::SUCCESS;
    }

    private function ingresarManual(): int
    {
        $fecha = $this->argument('fecha') ?? now()->toDateString();
        $compra = $this->ask('Tipo de cambio COMPRA (ej: 507.50)');
        $venta = $this->ask('Tipo de cambio VENTA (ej: 515.20)');

        if (! is_numeric($compra) || ! is_numeric($venta)) {
            $this->error('Los valores deben ser numéricos.');

            return self::FAILURE;
        }

        $tipoCambio = TipoCambio::updateOrCreate(
            ['fecha' => $fecha],
            [
                'compra' => $compra,
                'venta' => $venta,
                'source' => 'manual',
            ]
        );

        $this->info("Tipo de cambio manual guardado para {$tipoCambio->fecha->format('d/m/Y')}:");
        $this->table(
            ['', 'Valor'],
            [
                ['Compra', '₡ '.number_format((float) $tipoCambio->compra, 2)],
                ['Venta', '₡ '.number_format((float) $tipoCambio->venta, 2)],
            ]
        );

        return self::SUCCESS;
    }
}
