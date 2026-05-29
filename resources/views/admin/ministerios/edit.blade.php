@extends('layouts.admin')

@section('title', tenant_title('Editar Ministerio'))
@section('page-title', 'Editar Ministerio')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('admin.ministerios._form')
</div>
@endsection
