<?php

namespace Tests\Concerns;

use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use Tests\Support\ConIglesia;

/**
 * Escenario de las Tarjetas con QR de acceso: una iglesia con su administrador, personas con o sin cuenta y
 * cuentas de miembro con contrasena conocida (y su copia visible).
 */
trait CreaEscenarioTarjetas
{
    use ConIglesia;

    protected Tenant $iglesia;

    protected User $admin;

    protected function armarIglesia(): void
    {
        $this->iglesia = $this->crearIglesia('ibbla-tq', 'TQ');
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
    }

    protected function persona(string $nombre = 'Ana Mora', array $atributos = []): Persona
    {
        return Persona::create($atributos + [
            'nombre' => $nombre,
            'activo' => true,
            'tenant_id' => $this->iglesia->id,
        ]);
    }

    /** Persona con cuenta de rol miembro (correo o usuario) y contrasena conocida, con su copia visible. */
    protected function personaConCuenta(string $nombre, string $contrasena = 'secreta123', array $cuenta = []): Persona
    {
        $usuario = new User($cuenta + [
            'name' => $nombre,
            'email' => strtolower(str_replace(' ', '.', $nombre)).'@correo.test',
            'rol' => 'miembro',
            'tenant_id' => $this->iglesia->id,
            'tenant_role_id' => $this->iglesia->roles()->where('slug', 'miembro')->value('id'),
        ]);
        $usuario->establecerContrasena($contrasena);
        $usuario->save();

        $persona = $this->persona($nombre);
        $persona->forceFill(['user_id' => $usuario->id])->save();

        return $persona;
    }

    protected function urlTarjeta(Persona $persona): string
    {
        return '/t/'.$persona->asegurarTokenAcceso();
    }
}
