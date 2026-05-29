<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo automático mensual con los reportes adjuntos (Fase 7).
 * Se envía el día 1 de cada mes con el resumen del mes anterior.
 * Adjunta el PDF de asistencia mensual + Excel de ingresos + Excel de promesas.
 */
class ReporteMensualMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $mes,
        public int $año,
        public string $nombreMes,
        /** @var array<int,array{path:string,filename:string}> */
        public array $adjuntos = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "IBBSC — Reportes de {$this->nombreMes} {$this->año}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reporte-mensual',
            with: [
                'nombreMes' => $this->nombreMes,
                'año' => $this->año,
                'totalAdjuntos' => count($this->adjuntos),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn ($adj) => Attachment::fromPath($adj['path'])->as($adj['filename']),
            $this->adjuntos,
        );
    }
}
