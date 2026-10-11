<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\NavegadorResumen;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Historial de ingresos a la plataforma: quién entró, cuándo y a qué hora (solo admin).
 *
 * Es una vista de audit_logs limitada a los eventos de acceso (inicio de sesión, cierre
 * de sesión e intento fallido) que registra App\Listeners\RegistrarAccesoSubscriber.
 * Un admin de iglesia solo ve los de su iglesia; el super admin ve todos.
 */
class HistorialIngresosController extends Controller
{
    /** Valor del filtro "Evento" -> acción guardada en audit_logs. */
    private const EVENTOS = [
        'ingreso' => AuditLog::ACCION_LOGIN,
        'salida' => AuditLog::ACCION_LOGOUT,
        'fallido' => AuditLog::ACCION_LOGIN_FALLIDO,
    ];

    public function index(Request $request): View
    {
        [$filtros, $errores] = $this->filtros($request);
        $consulta = $this->consulta($request, $filtros);

        // Resumen del conjunto filtrado (no solo de la página): ingresos / salidas / fallidos.
        $totales = (clone $consulta)->reorder()
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        $logs = $consulta->paginate(25)->withQueryString();

        return view('admin.audit.ingresos', [
            'logs' => $logs,
            'filtros' => $filtros,
            // Lo que el usuario escribió, tal cual, para que el formulario no le borre lo que
            // tiene que corregir cuando un filtro se descarta por inválido.
            'entrada' => collect(['usuario', 'evento', 'fecha_inicio', 'fecha_fin'])
                ->mapWithKeys(fn ($campo) => [$campo => is_string($request->query($campo)) ? $request->query($campo) : null])
                ->all(),
            'errores' => $errores,
            'eventos' => array_keys(self::EVENTOS),
            'totales' => [
                'ingresos' => (int) ($totales[AuditLog::ACCION_LOGIN] ?? 0),
                'salidas' => (int) ($totales[AuditLog::ACCION_LOGOUT] ?? 0),
                'fallidos' => (int) ($totales[AuditLog::ACCION_LOGIN_FALLIDO] ?? 0),
            ],
            'hayFiltros' => collect($filtros)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty(),
            'zonaHoraria' => date_default_timezone_get(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse|RedirectResponse
    {
        [$filtros, $errores] = $this->filtros($request);

        // Con filtros inválidos no se exporta "todo" en silencio: se vuelve a la pantalla,
        // que explica qué corregir.
        if ($errores->isNotEmpty()) {
            return redirect()->route('admin.auditoria.ingresos', $request->query());
        }

        $consulta = $this->consulta($request, $filtros);
        $nombre = 'historial_ingresos_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($consulta) {
            $salida = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel respete tildes y eñes; ";" porque Excel en español
            // (Costa Rica incluida) usa punto y coma como separador de lista.
            fwrite($salida, "\xEF\xBB\xBF");
            $this->fila($salida, [
                'Fecha', 'Hora', 'Usuario', 'Correo', 'Evento', 'IP', 'Navegador', 'Sistema', 'Agente de usuario',
            ]);

            foreach ($consulta->cursor() as $log) {
                $resumen = NavegadorResumen::resumir($log->user_agent);

                $this->fila($salida, [
                    $log->created_at->format('d/m/Y'),
                    $log->created_at->format('H:i:s'),
                    $log->user_name,
                    $log->user_email,
                    $log->etiqueta_accion,
                    $log->ip_address,
                    $resumen['navegador'],
                    $resumen['sistema'],
                    $log->user_agent,
                ]);
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lee y valida los filtros de la URL. Se valida a mano (no con $request->validate())
     * porque en un GET eso redirige "atrás", y con una URL compartida con un filtro mal
     * formado volvería a la misma URL en bucle. Lo inválido se ignora y se informa.
     *
     * @return array{0: array{usuario: ?string, evento: ?string, fecha_inicio: ?string, fecha_fin: ?string}, 1: MessageBag}
     */
    private function filtros(Request $request): array
    {
        $validador = Validator::make($request->query(), [
            'usuario' => ['nullable', 'string', 'max:100'],
            'evento' => ['nullable', Rule::in(array_keys(self::EVENTOS))],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ], [
            'fecha_inicio.date_format' => 'La fecha de inicio no es válida.',
            'fecha_fin.date_format' => 'La fecha de fin no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'evento.in' => 'El evento indicado no existe.',
            'usuario.max' => 'El usuario a buscar admite hasta 100 caracteres.',
        ]);

        $errores = $validador->errors();
        $filtros = [];
        foreach (['usuario', 'evento', 'fecha_inicio', 'fecha_fin'] as $campo) {
            $valor = $errores->has($campo) ? null : $request->query($campo);
            $filtros[$campo] = is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
        }

        // Un rango incoherente se descarta completo: aplicar solo una mitad confundiría.
        if ($errores->has('fecha_fin') && $filtros['fecha_inicio'] !== null) {
            $filtros['fecha_inicio'] = null;
        }

        return [$filtros, $errores];
    }

    private function consulta(Request $request, array $filtros): Builder
    {
        $usuario = $request->user();

        // Aislamiento por iglesia. Un usuario sin iglesia que no sea super admin no tiene
        // nada que ver aquí (ResolveTenant ya lo habría sacado): se corta en vez de filtrar
        // por "tenant_id IS NULL", que mostraría los intentos fallidos de todas las iglesias.
        abort_unless($usuario->isSuperAdmin() || $usuario->tenant_id, 403);

        $consulta = AuditLog::accesos()->orderByDesc('created_at')->orderByDesc('id');

        if (! $usuario->isSuperAdmin()) {
            $consulta->where('tenant_id', $usuario->tenant_id);
        }

        if ($filtros['usuario'] !== null) {
            // % y _ del texto buscado se escapan: son comodines de LIKE.
            $patron = '%'.addcslashes($filtros['usuario'], '%_\\').'%';
            $consulta->where(function (Builder $q) use ($patron) {
                $q->where('user_name', 'like', $patron)->orWhere('user_email', 'like', $patron);
            });
        }

        if ($filtros['evento'] !== null) {
            $consulta->where('action', self::EVENTOS[$filtros['evento']]);
        }

        if ($filtros['fecha_inicio'] !== null) {
            $consulta->where('created_at', '>=', CarbonImmutable::createFromFormat('Y-m-d', $filtros['fecha_inicio'])->startOfDay());
        }
        if ($filtros['fecha_fin'] !== null) {
            $consulta->where('created_at', '<=', CarbonImmutable::createFromFormat('Y-m-d', $filtros['fecha_fin'])->endOfDay());
        }

        return $consulta;
    }

    /**
     * Escribe una fila CSV neutralizando la inyección de fórmulas: un texto que empiece
     * con = + - @ (o tabulador / retorno) se interpretaría como fórmula al abrirlo en Excel.
     * El User-Agent y el correo de un intento fallido los controla quien intenta entrar.
     *
     * @param  resource  $salida
     */
    private function fila($salida, array $valores): void
    {
        $limpios = array_map(function ($valor) {
            $texto = (string) $valor;

            return $texto !== '' && str_contains("=+-@\t\r", $texto[0]) ? "'".$texto : $texto;
        }, $valores);

        // escape '' = sin carácter de escape (RFC 4180) y sin aviso de deprecación en PHP 8.4.
        fputcsv($salida, $limpios, ';', '"', '', "\r\n");
    }
}
