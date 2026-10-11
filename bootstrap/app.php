<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\DetectMobileApp::class,
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'audit' => \App\Http\Middleware\AuditLogMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // El informe de una persona se comparte por WhatsApp y lo abre gente sin sesión: si el enlace
        // venció o está alterado se explica en español, en vez del «Acceso denegado» de la intranet.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('informe.persona')) {
                return response()->view('errors.informe-no-valido', [], 403);
            }
        });
    })->create();
