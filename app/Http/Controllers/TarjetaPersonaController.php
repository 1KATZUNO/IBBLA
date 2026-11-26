<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClaseAsistencia;
use App\Models\Persona;
use App\Models\User;
use App\Services\Tarjetas\TarjetasAcceso;
use App\Services\Tarjetas\TarjetasPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tarjetas con QR de acceso personal, lado del administrador (docs/plan-oct-2026/07-TARJETAS-QR.md): la tarjeta
 * flotante de Personas, su PDF, "Ver contrasena", "Restablecer acceso", "Generar QR nuevo" y la descarga masiva.
 * Solo el Administrador (grupo role:admin de routes/web.php); todo lo sensible queda en la auditoria.
 */
class TarjetaPersonaController extends Controller
{
    public function __construct(private TarjetasAcceso $tarjetas) {}

    /** Contenido de la tarjeta flotante (HTML que la pantalla de Personas pone dentro de su ventana). */
    public function show(Persona $persona)
    {
        return view('personas.tarjetas._tarjeta', $this->datosVentana($persona));
    }

    /** La tarjeta de una persona en PDF (una sola, arriba de una hoja carta con sus marcas de corte). */
    public function pdf(Persona $persona, TarjetasPdf $pdf): Response
    {
        $contenido = $pdf->render([$this->tarjetas->tarjeta($persona)], $this->tarjetas->marca(tenant()), 'Tarjeta de acceso');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="tarjeta-'.Str::slug($persona->nombre).'.pdf"',
        ]);
    }

    /**
     * "Ver contrasena": solo con el interruptor encendido y solo cuentas de rol miembro. Cada consulta queda en
     * la auditoria (quien vio la de quien; nunca el valor).
     */
    public function contrasena(Persona $persona): JsonResponse
    {
        abort_unless(User::contrasenaVisibleActiva(), 404);

        $usuario = $persona->user_id ? User::find($persona->user_id) : null;
        if (! $usuario || ! $usuario->esCuentaMiembro()) {
            return response()->json(['mensaje' => 'Solo se puede ver la contraseña de una cuenta de miembro.'], 422);
        }

        $contrasena = $usuario->contrasenaVisible();

        AuditLog::registrar('Ver contraseña de un miembro', [
            'persona_id' => $persona->id,
            'persona' => $persona->nombre,
            'usuario_id' => $usuario->id,
            'cuenta' => $usuario->identificador(),
            'habia_copia' => $contrasena !== null,
        ]);

        return response()->json([
            'contrasena' => $contrasena,
            'mensaje' => $contrasena === null
                ? 'No hay copia de esta contraseña: se creó antes de esta función o se cambió por correo. Usa «Restablecer acceso».'
                : null,
        ], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * "Restablecer acceso": borra la contrasena de la cuenta (queda una imposible de adivinar), cierra sus
     * sesiones y el proximo escaneo de la tarjeta le pide a la persona una nueva. Solo cuentas miembro.
     */
    public function restablecer(Request $request, Persona $persona): JsonResponse|RedirectResponse
    {
        $usuario = $persona->user_id ? User::find($persona->user_id) : null;
        if (! $usuario || ! $usuario->esCuentaMiembro() || $usuario->id === $request->user()->id) {
            return $this->responder($request, $persona, 'Solo se puede restablecer el acceso de una cuenta de miembro.', false);
        }

        $usuario->establecerContrasena(Str::random(64));
        $usuario->password_visible = null;
        $usuario->debe_crear_contrasena = true;
        $usuario->setRememberToken(Str::random(60));
        $usuario->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }

        AuditLog::registrar('Restablecer acceso de un miembro', [
            'persona_id' => $persona->id,
            'persona' => $persona->nombre,
            'usuario_id' => $usuario->id,
            'cuenta' => $usuario->identificador(),
        ]);

        return $this->responder($request, $persona, "Listo: la próxima vez que {$persona->nombre} escanee su tarjeta va a crear una contraseña nueva.");
    }

    /** "Generar QR nuevo": otro secreto; la tarjeta anterior deja de servir. */
    public function nuevoQr(Request $request, Persona $persona): JsonResponse|RedirectResponse
    {
        $tenia = (bool) $persona->token_acceso;
        $persona->renovarTokenAcceso();

        AuditLog::registrar('Generar QR nuevo de una persona', [
            'persona_id' => $persona->id,
            'persona' => $persona->nombre,
            'tenia_qr' => $tenia,
        ]);

        return $this->responder($request, $persona, 'Listo: hay un QR nuevo. La tarjeta anterior ya no sirve; imprime y entrega esta.');
    }

    /** Pantalla de la descarga masiva: a quienes incluye y el boton del PDF. */
    public function masivo(Request $request)
    {
        [$filtro, $clase] = $this->filtroMasivo($request);
        $clases = ClaseAsistencia::activas()->ordenadas()->get(['id', 'nombre']);

        return view('personas.tarjetas.masivo', [
            'filtro' => $filtro,
            'claseId' => $clase?->id,
            'clases' => $clases,
            'conteos' => [
                TarjetasAcceso::FILTRO_PROMESA => $this->contar(TarjetasAcceso::FILTRO_PROMESA),
                TarjetasAcceso::FILTRO_ACTIVAS => $this->contar(TarjetasAcceso::FILTRO_ACTIVAS),
            ],
            'conteoClase' => $clase ? $this->contar(TarjetasAcceso::FILTRO_CLASE, $clase->id) : null,
        ]);
    }

    /** El PDF masivo: 8 tarjetas por hoja carta, ordenadas por clase y nombre. ?accion=ver|descargar */
    public function masivoPdf(Request $request, TarjetasPdf $pdf): Response
    {
        [$filtro, $clase] = $this->filtroMasivo($request);
        $tarjetas = $this->tarjetas->tarjetas($filtro, $clase?->id, $clase?->nombre);

        $contenido = $pdf->render($tarjetas, $this->tarjetas->marca(tenant()));

        $nombre = 'tarjetas-'.match ($filtro) {
            TarjetasAcceso::FILTRO_ACTIVAS => 'personas-activas',
            TarjetasAcceso::FILTRO_CLASE => 'clase-'.Str::slug((string) $clase?->nombre),
            default => 'con-promesa',
        }.'.pdf';
        $disposicion = $request->query('accion') === 'descargar' ? 'attachment' : 'inline';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion.'; filename="'.$nombre.'"',
        ]);
    }

    // ------------------------------------------------------------------

    /** @return array{0: string, 1: ?ClaseAsistencia} */
    private function filtroMasivo(Request $request): array
    {
        $filtro = in_array($request->query('filtro'), TarjetasAcceso::FILTROS, true) ? $request->query('filtro') : TarjetasAcceso::FILTRO_PROMESA;
        $clase = null;
        if ($filtro === TarjetasAcceso::FILTRO_CLASE) {
            $clase = $request->filled('clase') ? ClaseAsistencia::find((int) $request->query('clase')) : null;
            if (! $clase) {
                $filtro = TarjetasAcceso::FILTRO_PROMESA;
            }
        }

        return [$filtro, $clase];
    }

    private function contar(string $filtro, ?int $claseId = null): int
    {
        return $this->tarjetas->consulta($filtro, $claseId)->count();
    }

    private function datosVentana(Persona $persona): array
    {
        return [
            'persona' => $persona,
            'tarjeta' => $this->tarjetas->tarjeta($persona),
            'cuenta' => $this->tarjetas->estadoCuenta($persona),
            'marca' => $this->tarjetas->marca(tenant()),
            'contrasenaVisibleActiva' => User::contrasenaVisibleActiva(),
        ];
    }

    /** La pantalla pide JSON (vuelve a pintar la ventana con el aviso); sin JavaScript, vuelve a Personas. */
    private function responder(Request $request, Persona $persona, string $mensaje, bool $ok = true): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $ok,
                'mensaje' => $mensaje,
                'html' => view('personas.tarjetas._tarjeta', $this->datosVentana($persona->fresh()))->render(),
            ], $ok ? 200 : 422);
        }

        return redirect()->route('personas.index')->with($ok ? 'success' : 'error', $mensaje);
    }
}
