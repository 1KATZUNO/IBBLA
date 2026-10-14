@extends('layouts.admin')

@section('title', tenant_title('Recuento '.$culto->fecha->format('d/m/Y')))
@section('page-title', 'Recuento de Sobres')

@section('content')
@php
    // $permisoEditar viene de la regla unica CultoPolicy::puedeEditarCulto (abierto: tesoreria; cerrado: solo el admin)
    $puedeEditar = $permisoEditar->allowed();
    $accionesVisibles = ! \App\Support\Permiso::seOculta($permisoEditar);
@endphp
<div class="space-y-6">
    @include('recuento.partials.encabezado')
    @include('recuento.partials.aviso-estado')
    @include('recuento.partials.sobres')
    @include('recuento.partials.movimientos')
    @include('recuento.partials.resumen-categorias')
    @include('recuento.partials.firmas')
</div>

@include('recuento.partials.modales')
@endsection
