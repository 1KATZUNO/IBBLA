@extends('layouts.admin')

@section('title', tenant_title('Clases'))
@section('page-title', 'App de Clases — Registro Digital')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl shadow-md p-6 border border-amber-200">
        <h2 class="text-xl font-semibold text-gray-800 mb-2">Selecciona una clase</h2>
        <p class="text-sm text-gray-600">
            Registro digital de Escuela Dominical: miembros, visitas, asistencia individual por domingo
            y visitación pastoral por semana. Reemplaza los cuadernos físicos tipo "Registro Horeb".
        </p>
    </div>

    @if($clases->isEmpty())
        <div class="bg-white rounded-2xl shadow p-12 text-center">
            <p class="text-gray-500 text-lg">No hay clases activas disponibles para ti.</p>
            <p class="text-sm text-gray-400 mt-2">Habla con el administrador para que te asigne una clase.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($clases as $clase)
                <a href="{{ route('clase-app.shell', $clase->slug) }}"
                   class="block rounded-2xl bg-white shadow-md hover:shadow-lg transition-all hover:scale-[1.02] overflow-hidden"
                   style="border-top: 6px solid {{ $clase->color }};">
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                 style="background:{{ $clase->color }}22; color:{{ $clase->color }};">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <span class="text-xs uppercase tracking-widest text-gray-400">Clase</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $clase->nombre }}</h3>
                        <p class="text-xs text-gray-500 mt-1"><code>{{ $clase->slug }}</code></p>
                        <div class="mt-4 inline-flex items-center gap-1 text-sm font-medium" style="color:{{ $clase->color }};">
                            Abrir app
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
