<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Manejar errores 403 (Forbidden)
        if ($e instanceof HttpException && $e->getStatusCode() === 403) {
            return response()->view('errors.403', [], 403);
        }

        // Manejar errores 404 (Not Found)
        if ($e instanceof HttpException && $e->getStatusCode() === 404) {
            return response()->view('errors.404', [], 404);
        }

        // Manejar errores 500 (Server Error) solo en producción
        if ($e instanceof HttpException && $e->getStatusCode() === 500 && !config('app.debug')) {
            return response()->view('errors.500', [], 500);
        }

        return parent::render($request, $e);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $request->expectsJson()
            ? response()->json(['message' => 'No autenticado.'], 401)
            : redirect()->guest(route('login'));
    }
}
