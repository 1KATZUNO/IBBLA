<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Persona extends Model
{
    use BelongsToTenant;

    protected $table = 'personas';

    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'direccion',
        'fecha_nacimiento',
        'pin',
        'password',
        'user_id',
        'activo',
        'notas',
        'tenant_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_nacimiento' => 'date',
    ];

    protected $hidden = [
        'password',
        // Secreto del QR de la tarjeta de acceso: nunca sale en un JSON (app de clases, buscadores...)
        'token_acceso',
    ];

    // --- Tarjeta con QR de acceso personal (docs/plan-oct-2026/07-TARJETAS-QR.md) ---

    /** Largo del secreto que va en la direccion /t/{token} del QR. */
    public const LARGO_TOKEN = 40;

    /**
     * El secreto del QR de la persona; si todavia no tiene, se genera y se guarda (la tarjeta nace la primera vez
     * que se pide).
     */
    public function asegurarTokenAcceso(): string
    {
        if (! $this->token_acceso) {
            $this->forceFill(['token_acceso' => self::nuevoTokenAcceso()])->save();
        }

        return $this->token_acceso;
    }

    /** "Generar QR nuevo": otro secreto; el QR anterior deja de servir desde ya. */
    public function renovarTokenAcceso(): string
    {
        $this->forceFill(['token_acceso' => self::nuevoTokenAcceso()])->save();

        return $this->token_acceso;
    }

    /**
     * 40 caracteres aleatorios (Str::random usa random_bytes, criptograficamente seguro): ~238 bits, no se deriva
     * del id ni del PIN. Se comprueba que no exista (la columna es unica).
     */
    public static function nuevoTokenAcceso(): string
    {
        do {
            $token = \Illuminate\Support\Str::random(self::LARGO_TOKEN);
        } while (static::withoutGlobalScopes()->where('token_acceso', $token)->exists());

        return $token;
    }

    public function sobres(): HasMany
    {
        return $this->hasMany(Sobre::class);
    }

    public function promesas(): HasMany
    {
        return $this->hasMany(Promesa::class);
    }

    public function compromisos(): HasMany
    {
        return $this->hasMany(Compromiso::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Activación de WhatsApp (CallMeBot): su número, su clave personal y su consentimiento. */
    public function activacionWhatsApp(): HasOne
    {
        return $this->hasOne(ActivacionWhatsApp::class);
    }

    public function ministerios(): BelongsToMany
    {
        return $this->belongsToMany(Ministerio::class, 'persona_ministerio')
            ->withPivot(['desde', 'hasta', 'es_lider'])
            ->withTimestamps();
    }

    /**
     * Ministerios donde la persona está actualmente activa (pivot.hasta is null).
     */
    public function ministeriosActivos(): BelongsToMany
    {
        return $this->ministerios()->wherePivotNull('hasta');
    }

    public function clasesAsistencia(): BelongsToMany
    {
        return $this->belongsToMany(ClaseAsistencia::class, 'clase_persona')
            ->withPivot(['es_maestro', 'tipo', 'convertida_de_visita_at', 'notas_clase'])
            ->withTimestamps();
    }

    public function esMaestroEn($claseId): bool
    {
        return $this->clasesAsistencia()
            ->wherePivot('clase_asistencia_id', $claseId)
            ->wherePivot('es_maestro', true)
            ->exists();
    }

    public function asistenciasIndividuales(): HasMany
    {
        return $this->hasMany(AsistenciaPersonaCulto::class);
    }

    public function visitacionesPastorales(): HasMany
    {
        return $this->hasMany(VisitacionPastoral::class);
    }
}
