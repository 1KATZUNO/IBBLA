@extends('layouts.admin')

@section('title', tenant_title('Recuento '.$culto->fecha->format('d/m/Y')))
@section('page-title', 'Recuento de Sobres')

@section('content')
@php
    // Quien puede modificar este culto: abierto, cualquiera de tesoreria; cerrado, solo el admin.
    $puedeEditar = ! $culto->cerrado || Auth::user()->isAdmin();
@endphp
<div class="space-y-6">
    @include('recuento.partials.encabezado')
    @include('recuento.partials.sobres')
    @include('recuento.partials.movimientos')
    @include('recuento.partials.resumen-categorias')
    @include('recuento.partials.firmas')
</div>

@include('recuento.partials.modales')
@endsection
