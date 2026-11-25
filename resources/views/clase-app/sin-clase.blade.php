@extends('layouts.admin')

@section('title', tenant_title('App de Clases'))
@section('page-title', 'App de Clases')

@section('content')
{{-- QE-016: el miembro que no es maestra de ninguna clase (antes veia "Selecciona una clase / No hay clases activas") --}}
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-2xl shadow p-8 text-center" role="status">
        <svg class="mx-auto h-12 w-12 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <h2 class="mt-4 text-xl font-semibold text-gray-900">No tienes una clase asignada.</h2>
        <p class="mt-2 text-gray-600">Pídele al administrador que te asigne una.</p>
        <div class="mt-6">
            <a href="{{ route(auth()->user()->rutaDeInicio()) }}" class="btn-primary">Volver al inicio</a>
        </div>
    </div>
</div>
@endsection
