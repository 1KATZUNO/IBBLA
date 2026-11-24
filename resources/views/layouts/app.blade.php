{{--
    Layout de componente (<x-app-layout>), usado por el perfil de usuario (Breeze).
    Reusa el mismo marco que el resto de la app (barra superior + menu lateral) en vez del
    de Breeze, para que "Mi cuenta" se vea igual que cualquier otra pantalla.

    Acepta el slot "header" (su texto pasa a ser el titulo de la pagina) y el contenido por $slot.
--}}
@php
    $tituloPagina = isset($header) ? trim(strip_tags((string) $header)) : '';
    // Titulos de Breeze que siguen en ingles
    $tituloPagina = ['Profile' => 'Mi cuenta'][$tituloPagina] ?? $tituloPagina;
@endphp
@extends('layouts.admin')

@section('page-title', $tituloPagina !== '' ? $tituloPagina : 'Mi cuenta')

@section('content')
    {{ $slot }}
@endsection
