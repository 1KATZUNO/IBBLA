@extends('layouts.admin')

@section('title', 'IBBSC - Nuevo Ministerio')
@section('page-title', 'Nuevo Ministerio')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('admin.ministerios._form')
</div>
@endsection
