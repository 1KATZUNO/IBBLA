@extends('layouts.admin')

@section('title', tenant_title('Editar Ministerio'))
@section('page-title', 'Editar Ministerio')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    @include('admin.ministerios._form')

    {{-- Quién está asignado: explica por qué no se puede eliminar y permite ir a quitarlo. --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Personas asignadas ({{ $personas->count() }})</h3>
        <p class="text-xs text-gray-500 mb-4">
            Las asignaciones se hacen desde la ficha de cada persona (Gestionar Personas &gt; Editar &gt; Ministerios).
        </p>

        @forelse($personas as $persona)
            @if($loop->first)
            <ul class="divide-y divide-gray-100">
            @endif
                <li class="py-2 flex items-center justify-between gap-3 text-sm">
                    <span>
                        <a href="{{ route('personas.edit', $persona) }}" class="text-blue-600 hover:underline">{{ $persona->nombre }}</a>
                        @if($persona->pivot->es_lider)
                            <span class="ml-2 px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs">Líder</span>
                        @endif
                    </span>
                    <span class="text-xs text-gray-500">
                        @if($persona->pivot->hasta)
                            Hasta {{ \Carbon\Carbon::parse($persona->pivot->hasta)->format('d/m/Y') }}
                        @elseif($persona->pivot->desde)
                            Desde {{ \Carbon\Carbon::parse($persona->pivot->desde)->format('d/m/Y') }}
                        @endif
                    </span>
                </li>
            @if($loop->last)
            </ul>
            @endif
        @empty
            <p class="text-sm text-gray-500">Nadie está asignado todavía. Este ministerio se puede eliminar desde la lista.</p>
        @endforelse
    </div>
</div>
@endsection
