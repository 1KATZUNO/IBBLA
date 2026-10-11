@extends('layouts.admin')

@section('title', tenant_title('Avisos de WhatsApp'))
@section('page-title', 'Avisos de WhatsApp')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <p class="text-sm text-gray-600">
            Cuando se crea un culto o un culto llega a {{ (int) config('whatsapp.avisos.sobres_umbral', 10) }} sobres, se prepara un aviso para
            cada líder con teléfono registrado. Toque <strong>Abrir WhatsApp</strong>: se abre el chat con el mensaje ya escrito y solo falta tocar «enviar».
            Después marque el aviso como enviado.
        </p>
        @if($envioAutomatico)
        <p class="mt-2 text-sm text-green-700">Envío automático activo: los avisos nuevos se envían solos. Aquí quedan los que no se pudieron enviar.</p>
        @endif

        <div class="mt-4 flex gap-2 border-b border-gray-200">
            <a href="{{ route('avisos.index') }}"
               class="px-4 py-2 text-sm font-medium border-b-2 {{ $pestana === 'pendientes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Pendientes
                @if($totalPendientes > 0)
                <span class="ml-1 px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">{{ $totalPendientes }}</span>
                @endif
            </a>
            <a href="{{ route('avisos.index', ['tab' => 'historial']) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 {{ $pestana === 'historial' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Historial
            </a>
        </div>
    </div>

    @if($pestana === 'pendientes')
        @forelse($grupos as $evento => $avisos)
        <section class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ \App\Models\AvisoWhatsApp::EVENTOS[$evento] ?? ucfirst(str_replace('_', ' ', $evento)) }}</h3>
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">{{ $avisos->count() }}</span>
            </div>
            <ul class="divide-y divide-gray-200">
                @foreach($avisos as $aviso)
                <li class="p-4 flex flex-col lg:flex-row lg:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ $aviso->titulo }}</p>
                        <p class="mt-1 text-sm text-gray-600">
                            Para <strong>{{ $aviso->destinatario_nombre }}</strong>
                            · {{ $aviso->telefono_formateado }}
                            · {{ $aviso->created_at->diffForHumans() }}
                        </p>
                        <details class="mt-2 text-sm text-gray-500">
                            <summary class="cursor-pointer">Ver mensaje</summary>
                            <p class="mt-1 p-3 bg-gray-50 rounded-md" style="white-space: pre-line;">{{ $aviso->mensaje }}</p>
                        </details>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $aviso->enlace_whatsapp }}" target="_blank" rel="noopener"
                           class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors">
                            Abrir WhatsApp
                        </a>
                        <form action="{{ route('avisos.enviado', $aviso) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 border border-gray-300 text-sm text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                                Marcar enviado
                            </button>
                        </form>
                        <form action="{{ route('avisos.descartar', $aviso) }}" method="POST"
                              onsubmit="return confirm('¿Descartar este aviso? No se enviará.');">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition-colors">
                                Descartar
                            </button>
                        </form>
                    </div>
                </li>
                @endforeach
            </ul>
        </section>
        @empty
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No hay avisos pendientes.
        </div>
        @endforelse
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aviso</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Para</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Atendido</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($historial as $aviso)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <span class="block font-medium">{{ $aviso->titulo }}</span>
                                <span class="block text-xs text-gray-500">{{ $aviso->etiqueta_evento }} · {{ $aviso->created_at->format('d/m/Y H:i') }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                {{ $aviso->destinatario_nombre }}<br>
                                <span class="text-xs text-gray-500">{{ $aviso->telefono_formateado }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $aviso->estado === 'enviado' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $aviso->estado === 'enviado' ? 'Enviado' : 'Descartado' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                {{ $aviso->updated_at->format('d/m/Y H:i') }}<br>
                                <span class="text-xs text-gray-500">
                                    @if($aviso->atendidoPor)
                                        por {{ $aviso->atendidoPor->name }}
                                    @elseif($aviso->estado === 'enviado')
                                        enviado por el sistema
                                    @endif
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-500">Todavía no hay avisos atendidos.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            {{ $historial->links() }}
        </div>
    @endif
</div>
@endsection
