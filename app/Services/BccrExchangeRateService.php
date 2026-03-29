<?php

namespace App\Services;

use App\Models\TipoCambio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BccrExchangeRateService
{
    /**
     * API REST SDDE del BCCR (nuevo, vigente desde abril 2025).
     * Retorna JSON, usa Bearer token para autenticación.
     */
    private const BASE_URL = 'https://apim.bccr.fi.cr/SDDE/api/Bccr.GE.SDDE.Publico.Indicadores.API';

    private const INDICADOR_COMPRA = 317;

    private const INDICADOR_VENTA = 318;

    private const CACHE_KEY = 'bccr_tipo_cambio_hoy';

    private const CACHE_TTL_MINUTES = 60;

    private string $token;

    public function __construct()
    {
        $this->token = config('services.bccr.token', '');
    }

    /**
     * Obtiene el tipo de cambio del día. Usa cache y BD como fallback.
     */
    public function obtenerHoy(): ?TipoCambio
    {
        $hoy = Carbon::today()->toDateString();

        // 1. Buscar en BD primero
        $existente = TipoCambio::where('fecha', $hoy)->first();
        if ($existente) {
            return $existente;
        }

        // 2. Intentar obtener de BCCR (con cache para no saturar)
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_MINUTES * 60, function () use ($hoy) {
            return $this->fetchYGuardar($hoy) ?? TipoCambio::masReciente();
        });
    }

    /**
     * Obtiene el tipo de cambio para una fecha específica.
     */
    public function obtenerPorFecha(string $fecha): ?TipoCambio
    {
        $existente = TipoCambio::where('fecha', $fecha)->first();
        if ($existente) {
            return $existente;
        }

        return $this->fetchYGuardar($fecha) ?? TipoCambio::deFecha($fecha);
    }

    /**
     * Consulta la API SDDE del BCCR y guarda en BD.
     */
    public function fetchYGuardar(string $fecha): ?TipoCambio
    {
        if (empty($this->token)) {
            Log::warning('BCCR: Token no configurado. Configure BCCR_TOKEN en .env');

            return null;
        }

        try {
            $compra = $this->consultarIndicador(self::INDICADOR_COMPRA, $fecha);
            $venta = $this->consultarIndicador(self::INDICADOR_VENTA, $fecha);

            if ($compra === null || $venta === null) {
                Log::warning("BCCR: No se obtuvieron datos para fecha {$fecha}");

                return null;
            }

            return TipoCambio::updateOrCreate(
                ['fecha' => $fecha],
                [
                    'compra' => $compra,
                    'venta' => $venta,
                    'source' => 'bccr',
                ]
            );
        } catch (\Exception $e) {
            Log::error("BCCR: Error al obtener tipo de cambio: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Consulta un indicador específico via API REST SDDE.
     * Endpoint: /indicadoresEconomicos/{codigo}/series
     * Formato de fecha: yyyy/mm/dd
     * Auth: Bearer token en header
     * Respuesta JSON: { estado: true, datos: [{ series: [{ fecha, valorDatoPorPeriodo }] }] }
     */
    private function consultarIndicador(int $indicador, string $fecha): ?float
    {
        $fechaFormateada = Carbon::parse($fecha)->format('Y/m/d');

        $url = self::BASE_URL."/indicadoresEconomicos/{$indicador}/series";

        $response = Http::timeout(15)
            ->withToken($this->token)
            ->acceptJson()
            ->get($url, [
                'fechaInicio' => $fechaFormateada,
                'fechaFin' => $fechaFormateada,
                'idioma' => 'ES',
            ]);

        if (! $response->successful()) {
            Log::error("BCCR SDDE: HTTP {$response->status()} para indicador {$indicador}");

            return null;
        }

        return $this->parsearRespuestaJson($response->json(), $indicador);
    }

    /**
     * Parsea la respuesta JSON del API SDDE.
     *
     * Estructura esperada:
     * {
     *   "estado": true,
     *   "mensaje": "Consulta exitosa",
     *   "datos": [{
     *     "codigoIndicador": "317",
     *     "series": [{ "fecha": "2026-03-23", "valorDatoPorPeriodo": 463.24 }]
     *   }]
     * }
     */
    private function parsearRespuestaJson(?array $json, int $indicador): ?float
    {
        if (! $json || ! ($json['estado'] ?? false)) {
            Log::warning('BCCR SDDE: Respuesta inválida o estado=false', [
                'indicador' => $indicador,
                'mensaje' => $json['mensaje'] ?? 'Sin mensaje',
            ]);

            return null;
        }

        $datos = $json['datos'] ?? [];
        if (empty($datos)) {
            return null;
        }

        $series = $datos[0]['series'] ?? [];
        if (empty($series)) {
            return null;
        }

        $valor = $series[0]['valorDatoPorPeriodo'] ?? null;

        return $valor !== null ? (float) $valor : null;
    }

    /**
     * Limpia el cache del tipo de cambio.
     */
    public function limpiarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Verifica si las credenciales están configuradas.
     */
    public function credencialesConfiguradas(): bool
    {
        return ! empty($this->token);
    }
}
