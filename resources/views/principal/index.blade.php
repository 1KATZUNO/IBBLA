@extends('layouts.admin')

@section('title', 'IBBSC - Principal')
@section('page-title', 'Iglesia Bíblica Bautista Santa Cruz')

@section('content')
<div class="space-y-6">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-xl overflow-hidden">
        <div class="px-8 py-12 text-center text-white">
            <div class="flex justify-center mb-6">
                <img src="{{ asset('images/Logo.png') }}" alt="IBBSC Logo" class="w-32 h-32 object-contain drop-shadow-2xl">
            </div>
            <h1 class="text-4xl font-bold mb-4">Bienvenido a la Iglesia Bíblica Bautista Santa Cruz</h1>
            <p class="text-xl text-blue-100 max-w-2xl mx-auto">
                Una comunidad de fe comprometida con el amor de Cristo y el servicio a Dios
            </p>
        </div>
    </div>

    <!-- Información Principal -->
    <div class="grid md:grid-cols-2 gap-6">
        <!-- Nuestra Misión -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Nuestra Misión</h2>
            </div>
            <p class="text-gray-600 leading-relaxed">
                Proclamar el evangelio de Jesucristo, edificar a los creyentes en la fe y servir a nuestra comunidad con amor y dedicación, siguiendo las enseñanzas de la Palabra de Dios.
            </p>
        </div>

        <!-- Nuestra Visión -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Nuestra Visión</h2>
            </div>
            <p class="text-gray-600 leading-relaxed">
                Ser una iglesia que impacte a Santa Cruz y más allá, formando discípulos comprometidos con Cristo, viviendo en santidad y extendiendo el Reino de Dios a través del amor y la verdad.
            </p>
        </div>
    </div>

    <!-- Valores -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Nuestros Valores</h2>
        <div class="grid md:grid-cols-3 gap-6">
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </div>
                <h3 class="font-bold text-lg text-gray-800 mb-2">Amor</h3>
                <p class="text-gray-600 text-sm">Amar a Dios sobre todas las cosas y a nuestro prójimo como a nosotros mismos</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="font-bold text-lg text-gray-800 mb-2">Integridad</h3>
                <p class="text-gray-600 text-sm">Vivir con transparencia y honestidad según los principios bíblicos</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3 class="font-bold text-lg text-gray-800 mb-2">Comunidad</h3>
                <p class="text-gray-600 text-sm">Crecer juntos en fe, apoyándonos y edificándonos mutuamente</p>
            </div>
        </div>
    </div>

    <!-- Redes Sociales -->
    <div class="bg-gradient-to-r from-purple-700 to-blue-900 rounded-lg shadow-md p-6 text-white">
        <h2 class="text-2xl font-bold mb-4 text-center">Síguenos en Redes Sociales</h2>
        <p class="text-center mb-6">Mantente conectado con nuestra comunidad y conoce nuestras actividades</p>
        <div class="flex justify-center gap-6">
            <a href="https://www.instagram.com/ibb_santacruz?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="flex items-center gap-3 bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-lg transition-all duration-200 hover:scale-105">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                <span class="font-semibold">Instagram</span>
            </a>
            <a href="https://www.facebook.com/iglesia.biblica.bautista.santa.cruz" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="flex items-center gap-3 bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-lg transition-all duration-200 hover:scale-105">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span class="font-semibold">Facebook</span>
            </a>
        </div>
    </div>
</div>
@endsection
