<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Activación de WhatsApp (CallMeBot) de una persona o de un usuario del sistema: su número, su clave
 * personal y el consentimiento (cuándo, con qué texto). Ver la migración para el detalle.
 *
 * La clave se guarda cifrada y nunca sale en JSON ni se muestra: en pantalla solo se dice si hay una.
 * Las activaciones se crean y se actualizan desde ActivacionWhatsAppService, nunca con datos de un
 * formulario tal cual.
 */
class ActivacionWhatsApp extends Model
{
    use BelongsToTenant;

    /** Hay clave, está activa y es del número que el receptor tiene registrado hoy. */
    public const ESTADO_ACTIVA = 'activa';

    /** La persona la desactivó (o nunca tuvo clave): no hay clave guardada. */
    public const ESTADO_DESACTIVADA = 'desactivada';

    /** El receptor cambió su teléfono después de activar: la clave era de otro número y ya no sirve. */
    public const ESTADO_NUMERO_CAMBIO = 'numero_cambio';

    /** La clave guardada no se puede leer (p. ej. cambió la APP_KEY): hay que pegar el enlace otra vez. */
    public const ESTADO_ILEGIBLE = 'ilegible';

    protected $table = 'activaciones_whatsapp';

    protected $fillable = [
        'tenant_id',
        'persona_id',
        'user_id',
        'telefono',
        'apikey',
        'activo',
        'consentimiento_at',
        'consentimiento_version',
        'consentimiento_ip',
        'consentimiento_agente',
        'revocado_at',
        'verificado_at',
        'ultimo_envio_at',
        'ultimo_error',
    ];

    /** La clave nunca debe salir al serializar el modelo (JSON, logs, vistas que hagan toArray). */
    protected $hidden = ['apikey'];

    protected function casts(): array
    {
        return [
            'apikey' => 'encrypted',
            'activo' => 'boolean',
            'consentimiento_at' => 'datetime',
            'revocado_at' => 'datetime',
            'verificado_at' => 'datetime',
            'ultimo_envio_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Una activación es de una persona o de un usuario, nunca de los dos ni de ninguno.
        static::saving(function (self $activacion) {
            if (($activacion->persona_id === null) === ($activacion->user_id === null)) {
                throw new LogicException('Una activación de WhatsApp es de una persona o de un usuario, no de ambos ni de ninguno.');
            }
        });
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true)->whereNotNull('apikey');
    }

    /**
     * La clave en claro, solo para enviar. Null si no hay o si no se puede descifrar (otra APP_KEY):
     * en ese caso el receptor tiene que pegar su enlace otra vez, y nada se cae mientras tanto.
     */
    public function clave(): ?string
    {
        try {
            $valor = $this->apikey;
        } catch (DecryptException) {
            return null;
        }

        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    /**
     * En qué situación está frente al teléfono que el receptor tiene registrado HOY (ya normalizado,
     * o null si el que tiene no sirve).
     */
    public function estadoFrente(?string $telefonoRegistrado): string
    {
        if (! $this->activo) {
            return self::ESTADO_DESACTIVADA;
        }

        if ($this->clave() === null) {
            return self::ESTADO_ILEGIBLE;
        }

        if ($telefonoRegistrado === null || $telefonoRegistrado !== $this->telefono) {
            return self::ESTADO_NUMERO_CAMBIO;
        }

        return self::ESTADO_ACTIVA;
    }

    /** ¿Se puede enviar con esta clave a un receptor cuyo teléfono registrado hoy es este? */
    public function utilizablePara(?string $telefonoRegistrado): bool
    {
        return $this->estadoFrente($telefonoRegistrado) === self::ESTADO_ACTIVA;
    }

    /** «+506 8888-7777». */
    public function getTelefonoFormateadoAttribute(): string
    {
        return WhatsAppService::formatearTelefono($this->telefono);
    }

    /**
     * Deja constancia del último envío: cuándo y, si falló, por qué (el mensaje ya viene sin la clave).
     * Una prueba exitosa además lo deja como verificado.
     */
    public function registrarEnvio(?string $error, bool $esPrueba = false): void
    {
        $this->forceFill([
            'ultimo_envio_at' => now(),
            'ultimo_error' => $error === null ? null : mb_substr($error, 0, 250),
        ]);

        if ($error === null && $esPrueba) {
            $this->verificado_at = now();
        }

        $this->save();
    }
}
