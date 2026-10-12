<?php

namespace App\Http\Controllers;

use App\Models\AvisoWhatsApp;
use App\Services\WhatsApp\AuditoriaWhatsApp;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Panel de avisos de WhatsApp (admin y tesorero): los avisos pendientes agrupados por evento, cada
 * uno con su botón «Abrir WhatsApp» (wa.me con el mensaje escrito), y el historial de los atendidos.
 *
 * Es compartido: cualquier admin o tesorero de la iglesia ve todos los avisos y puede atenderlos,
 * porque el que manda el WhatsApp es quien tiene el teléfono a mano, no necesariamente el destinatario.
 */
class AvisoWhatsAppController extends Controller
{
    public function index(Request $request): View
    {
        $pestana = $request->query('tab') === 'historial' ? 'historial' : 'pendientes';

        $pendientes = AvisoWhatsApp::pendientes()->orderBy('created_at')->orderBy('id')->get();

        $historial = $pestana === 'historial'
            ? AvisoWhatsApp::historial()->with('atendidoPor')->orderByDesc('updated_at')->orderByDesc('id')->paginate(25)->withQueryString()
            : null;

        return view('avisos.index', [
            'pestana' => $pestana,
            'grupos' => $this->agruparPorEvento($pendientes),
            'totalPendientes' => $pendientes->count(),
            'historial' => $historial,
            'envioAutomatico' => app(WhatsAppService::class)->puedeEnviar(),
        ]);
    }

    public function marcarEnviado(Request $request, AvisoWhatsApp $aviso): RedirectResponse
    {
        if (! $aviso->estaPendiente()) {
            return $this->yaAtendido();
        }

        $aviso->marcarEnviado($request->user());
        AuditoriaWhatsApp::registrar($request, 'Marcar aviso de WhatsApp como enviado', 'avisos.enviado', $this->detalles($aviso));

        return redirect()->route('avisos.index')
            ->with('success', "Aviso para {$aviso->destinatario_nombre} marcado como enviado.");
    }

    public function descartar(Request $request, AvisoWhatsApp $aviso): RedirectResponse
    {
        if (! $aviso->estaPendiente()) {
            return $this->yaAtendido();
        }

        $aviso->descartar($request->user());
        AuditoriaWhatsApp::registrar($request, 'Descartar aviso de WhatsApp', 'avisos.descartar', $this->detalles($aviso));

        return redirect()->route('avisos.index')
            ->with('success', "Aviso para {$aviso->destinatario_nombre} descartado.");
    }

    /**
     * Pendientes agrupados por evento, en el orden de AvisoWhatsApp::EVENTOS (los eventos que no
     * estén ahí, al final).
     *
     * @param  Collection<int, AvisoWhatsApp>  $pendientes
     * @return Collection<string, Collection<int, AvisoWhatsApp>>
     */
    private function agruparPorEvento(Collection $pendientes): Collection
    {
        $orden = array_keys(AvisoWhatsApp::EVENTOS);

        return $pendientes->groupBy('evento')->sortBy(function ($avisos, $evento) use ($orden) {
            $posicion = array_search($evento, $orden, true);

            return $posicion === false ? count($orden) : $posicion;
        });
    }

    private function yaAtendido(): RedirectResponse
    {
        return redirect()->route('avisos.index')->with('error', 'Ese aviso ya no estaba pendiente: alguien más lo atendió.');
    }

    /** @return array<string, mixed> */
    private function detalles(AvisoWhatsApp $aviso): array
    {
        return [
            'aviso_id' => $aviso->id,
            'evento' => $aviso->evento,
            'destinatario' => $aviso->destinatario_nombre,
            'telefono' => $aviso->destinatario_telefono,
        ];
    }
}
