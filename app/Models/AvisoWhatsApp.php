<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso de WhatsApp para un destinatario (un líder con rol configurado y teléfono).
 *
 * Nace «pendiente». Con el driver link alguien abre el enlace de WhatsApp desde el panel /avisos y
 * lo marca «enviado» (o lo «descarta»); con el driver meta un job lo envía y lo marca solo.
 */
class AvisoWhatsApp extends Model
{
    use BelongsToTenant;

    public const PENDIENTE = 'pendiente';

    public const ENVIADO = 'enviado';

    public const DESCARTADO = 'descartado';

    public const EVENTO_CULTO_CREADO = 'culto_creado';

    public const EVENTO_SOBRES_ALCANZADOS = 'sobres_alcanzados';

    /** Eventos con su título de grupo en el panel, en el orden en que se muestran. */
    public const EVENTOS = [
        self::EVENTO_CULTO_CREADO => 'Cultos creados',
        self::EVENTO_SOBRES_ALCANZADOS => 'Sobres alcanzados',
    ];

    protected $table = 'avisos_whatsapp';

    protected $fillable = [
        'tenant_id',
        'evento',
        'titulo',
        'mensaje',
        'destinatario_nombre',
        'destinatario_telefono',
        'user_id',
        'enlace',
        'estado',
        'enviado_at',
        'enviado_por',
    ];

    protected function casts(): array
    {
        return [
            'enviado_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::PENDIENTE);
    }

    /** Los que ya se atendieron: enviados y descartados. */
    public function scopeHistorial(Builder $query): Builder
    {
        return $query->whereIn('estado', [self::ENVIADO, self::DESCARTADO]);
    }

    /** Cantidad de avisos pendientes de la iglesia del usuario que navega (para el contador del menú). */
    public static function contarPendientes(): int
    {
        return static::pendientes()->count();
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function getEtiquetaEventoAttribute(): string
    {
        return self::EVENTOS[$this->evento] ?? ucfirst(str_replace('_', ' ', (string) $this->evento));
    }

    public function getTelefonoFormateadoAttribute(): string
    {
        return WhatsAppService::formatearTelefono($this->destinatario_telefono);
    }

    /** Enlace wa.me que abre el chat con el mensaje ya escrito; sale del mismo texto que se guardó. */
    public function getEnlaceWhatsappAttribute(): ?string
    {
        return app(WhatsAppService::class)->enlace($this->destinatario_telefono, (string) $this->mensaje);
    }

    /** @param User|null $por quien lo marcó; null si lo envió el sistema (driver meta) */
    public function marcarEnviado(?User $por = null): void
    {
        $this->forceFill([
            'estado' => self::ENVIADO,
            'enviado_at' => now(),
            'enviado_por' => $por?->id,
        ])->save();
    }

    public function descartar(User $por): void
    {
        $this->forceFill([
            'estado' => self::DESCARTADO,
            'enviado_por' => $por->id,
        ])->save();
    }
}
