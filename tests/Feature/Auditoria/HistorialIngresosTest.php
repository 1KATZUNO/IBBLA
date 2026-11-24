<?php

namespace Tests\Feature\Auditoria;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Historial de ingresos a la plataforma: registro de Login / Logout / Failed en audit_logs
 * y la pantalla admin (con filtros y exportación CSV) que los muestra.
 */
class HistorialIngresosTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    private const UA_CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36';

    private Tenant $iglesia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin', ['name' => 'Ana Admin', 'email' => 'ana@ibbla.test']);
    }

    private function acceso(array $atributos = []): AuditLog
    {
        return AuditLog::forceCreate($atributos + [
            'tenant_id' => $this->iglesia->id,
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'user_email' => $this->admin->email,
            'ip_address' => '10.0.0.1',
            'user_agent' => self::UA_CHROME_WINDOWS,
            'method' => 'POST',
            'route' => 'login',
            'action' => AuditLog::ACCION_LOGIN,
            'details' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ================================================================ registro de eventos

    public function test_un_login_exitoso_crea_el_registro_con_quien_cuando_y_desde_donde(): void
    {
        $this->travelTo('2026-10-09 14:35:20');

        $this->withHeader('User-Agent', self::UA_CHROME_WINDOWS)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/login', ['email' => 'ana@ibbla.test', 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($this->admin);

        $this->assertDatabaseCount('audit_logs', 1);
        $registro = AuditLog::firstOrFail();
        $this->assertSame(AuditLog::ACCION_LOGIN, $registro->action);
        $this->assertSame($this->admin->id, $registro->user_id);
        $this->assertSame('Ana Admin', $registro->user_name);
        $this->assertSame('ana@ibbla.test', $registro->user_email);
        $this->assertSame($this->iglesia->id, $registro->tenant_id);
        $this->assertSame('203.0.113.9', $registro->ip_address);
        $this->assertSame(self::UA_CHROME_WINDOWS, $registro->user_agent);
        $this->assertSame('POST', $registro->method);
        $this->assertSame('login', $registro->route);
        $this->assertSame('2026-10-09 14:35:20', $registro->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(['guard' => 'web', 'remember' => false], json_decode($registro->details, true));
    }

    public function test_un_login_se_registra_una_sola_vez(): void
    {
        // Guarda contra el doble registro del listener (descubrimiento automático + Event::subscribe).
        $this->post('/login', ['email' => 'ana@ibbla.test', 'password' => 'password']);

        $this->assertSame(1, AuditLog::where('action', AuditLog::ACCION_LOGIN)->count());
    }

    public function test_el_cierre_de_sesion_se_registra(): void
    {
        $this->actingAs($this->admin)
            ->withHeader('User-Agent', self::UA_CHROME_WINDOWS)
            ->post('/logout')
            ->assertRedirect();

        $this->assertGuest();
        $registro = AuditLog::where('action', AuditLog::ACCION_LOGOUT)->firstOrFail();
        $this->assertSame($this->admin->id, $registro->user_id);
        $this->assertSame('ana@ibbla.test', $registro->user_email);
        $this->assertSame($this->iglesia->id, $registro->tenant_id);
        $this->assertSame('logout', $registro->route);
    }

    public function test_un_intento_fallido_con_un_correo_existente_se_registra_sin_guardar_la_contrasena(): void
    {
        $this->post('/login', ['email' => 'ana@ibbla.test', 'password' => 'clave-equivocada-123']);

        $this->assertGuest();
        $registro = AuditLog::where('action', AuditLog::ACCION_LOGIN_FALLIDO)->firstOrFail();
        $this->assertSame($this->admin->id, $registro->user_id);
        $this->assertSame('ana@ibbla.test', $registro->user_email);
        $this->assertSame($this->iglesia->id, $registro->tenant_id);
        $this->assertTrue(json_decode($registro->details, true)['usuario_existe']);

        $this->assertStringNotContainsString('clave-equivocada-123', json_encode($registro->getAttributes()));
    }

    public function test_un_intento_fallido_con_un_correo_inexistente_queda_sin_usuario_ni_iglesia(): void
    {
        $this->post('/login', ['email' => 'intruso@example.com', 'password' => 'lo-que-sea-123']);

        $registro = AuditLog::where('action', AuditLog::ACCION_LOGIN_FALLIDO)->firstOrFail();
        $this->assertNull($registro->user_id);
        $this->assertNull($registro->tenant_id);
        $this->assertSame('intruso@example.com', $registro->user_email);
        $this->assertFalse(json_decode($registro->details, true)['usuario_existe']);
    }

    public function test_el_login_de_la_app_de_asistencia_tambien_se_registra(): void
    {
        $this->postJson('/asistencia-app/login', ['email' => 'ana@ibbla.test', 'password' => 'password'])
            ->assertOk();

        $this->assertSame(1, AuditLog::where('action', AuditLog::ACCION_LOGIN)->count());
    }

    public function test_un_fallo_al_auditar_nunca_impide_iniciar_sesion(): void
    {
        AuditLog::creating(function () {
            throw new \RuntimeException('la tabla de auditoria no responde');
        });

        $this->post('/login', ['email' => 'ana@ibbla.test', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($this->admin);
    }

    // ================================================================ pantalla

    public function test_el_admin_ve_fecha_y_hora_usuario_correo_evento_ip_y_navegador(): void
    {
        $this->acceso(['created_at' => '2026-10-09 14:35:20']);

        $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos'))
            ->assertOk()
            ->assertSeeInOrder([
                '09/10/2026 14:35',
                'Ana Admin',
                'ana@ibbla.test',
                'Inicio de sesión',
                '10.0.0.1',
                'Chrome 118 · Windows',
            ]);
    }

    public function test_solo_se_listan_eventos_de_acceso_no_el_resto_de_la_auditoria(): void
    {
        $this->acceso(['user_name' => 'Fila de acceso']);
        $this->acceso(['user_name' => 'Fila de peticion', 'action' => 'recuento.store', 'route' => 'recuento.store', 'method' => 'POST']);

        $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos'))
            ->assertOk()
            ->assertSee('Fila de acceso')
            ->assertDontSee('Fila de peticion');
    }

    public function test_los_mas_recientes_van_primero(): void
    {
        $this->acceso(['user_name' => 'Antiguo Uno', 'created_at' => '2026-09-01 08:00:00']);
        $this->acceso(['user_name' => 'Reciente Dos', 'created_at' => '2026-10-09 08:00:00']);

        $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos'))
            ->assertSeeInOrder(['Reciente Dos', 'Antiguo Uno']);
    }

    public function test_filtra_por_usuario_nombre_o_correo(): void
    {
        $this->acceso(['user_name' => 'Beto Tesorero', 'user_email' => 'beto@ibbla.test']);
        $this->acceso(['user_name' => 'Carla Asistente', 'user_email' => 'carla@ibbla.test']);

        $this->actingAs($this->admin);
        $this->get(route('admin.auditoria.ingresos', ['usuario' => 'beto']))
            ->assertSee('Beto Tesorero')->assertDontSee('Carla Asistente');
        $this->get(route('admin.auditoria.ingresos', ['usuario' => 'carla@']))
            ->assertSee('Carla Asistente')->assertDontSee('Beto Tesorero');
    }

    public function test_el_filtro_de_usuario_trata_porcentaje_y_guion_bajo_como_texto(): void
    {
        $this->acceso(['user_name' => 'Beto']);

        $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos', ['usuario' => '%']))
            ->assertOk()->assertDontSee('Beto');
    }

    public function test_filtra_por_rango_de_fechas_incluyendo_ambos_extremos_completos(): void
    {
        $this->acceso(['user_name' => 'Fila Previa', 'created_at' => '2026-09-30 23:59:59']);
        $this->acceso(['user_name' => 'Fila Primer Instante', 'created_at' => '2026-10-01 00:00:00']);
        $this->acceso(['user_name' => 'Fila Ultimo Instante', 'created_at' => '2026-10-05 23:59:59']);
        $this->acceso(['user_name' => 'Fila Posterior', 'created_at' => '2026-10-06 00:00:00']);

        $this->actingAs($this->admin)
            ->get(route('admin.auditoria.ingresos', ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-05']))
            ->assertOk()
            ->assertSee('Fila Primer Instante')->assertSee('Fila Ultimo Instante')
            ->assertDontSee('Fila Previa')->assertDontSee('Fila Posterior');
    }

    public function test_filtra_por_tipo_de_evento(): void
    {
        $this->acceso(['user_name' => 'Entro Bien']);
        $this->acceso(['user_name' => 'Salio Bien', 'action' => AuditLog::ACCION_LOGOUT]);
        $this->acceso(['user_name' => 'Fallo Clave', 'action' => AuditLog::ACCION_LOGIN_FALLIDO]);

        $this->actingAs($this->admin);
        $this->get(route('admin.auditoria.ingresos', ['evento' => 'fallido']))
            ->assertSee('Fallo Clave')->assertDontSee('Entro Bien')->assertDontSee('Salio Bien');
        $this->get(route('admin.auditoria.ingresos', ['evento' => 'salida']))
            ->assertSee('Salio Bien')->assertDontSee('Fallo Clave');
    }

    public function test_el_resumen_cuenta_el_conjunto_filtrado_no_solo_la_pagina(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->acceso();
        }
        $this->acceso(['action' => AuditLog::ACCION_LOGOUT]);
        $this->acceso(['action' => AuditLog::ACCION_LOGIN_FALLIDO]);
        $this->acceso(['action' => AuditLog::ACCION_LOGIN_FALLIDO]);

        $respuesta = $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos'));

        $this->assertSame(['ingresos' => 30, 'salidas' => 1, 'fallidos' => 2], $respuesta->viewData('totales'));
    }

    public function test_pagina_de_a_25_y_conserva_los_filtros_en_los_enlaces(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->acceso(['user_name' => sprintf('Usuario %02d', $i), 'created_at' => now()->subMinutes(31 - $i)]);
        }

        $pagina1 = $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos', ['usuario' => 'Usuario']));
        $this->assertCount(25, $pagina1->viewData('logs'));
        $pagina1->assertSee('usuario=Usuario', false)->assertSee('page=2', false);

        $pagina2 = $this->get(route('admin.auditoria.ingresos', ['usuario' => 'Usuario', 'page' => 2]));
        $this->assertCount(5, $pagina2->viewData('logs'));
    }

    public function test_un_rango_con_fin_anterior_al_inicio_muestra_el_error_y_no_aplica_el_filtro(): void
    {
        $this->acceso(['user_name' => 'Sigue Visible', 'created_at' => '2026-10-01 10:00:00']);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('admin.auditoria.ingresos', ['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01']));

        $respuesta->assertOk()
            ->assertSee('La fecha de fin no puede ser anterior a la fecha de inicio.')
            ->assertSee('Sigue Visible')
            // el formulario conserva lo escrito para que se pueda corregir
            ->assertSee('value="2026-10-10"', false)
            ->assertSee('value="2026-10-01"', false);
        $this->assertTrue($respuesta->viewData('errores')->has('fecha_fin'));
    }

    public function test_una_fecha_mal_formada_o_un_evento_desconocido_no_rompen_la_pantalla(): void
    {
        $this->acceso();

        $this->actingAs($this->admin)
            ->get(route('admin.auditoria.ingresos', ['fecha_inicio' => 'ayer', 'evento' => 'x', 'usuario' => ['a']]))
            ->assertOk()
            ->assertSee('La fecha de inicio no es válida.');
    }

    public function test_sin_registros_explica_que_aun_no_hay_ingresos(): void
    {
        $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos'))
            ->assertOk()
            ->assertSee('Todavía no hay ingresos registrados');
    }

    public function test_un_navegador_con_user_agent_hostil_se_escapa_en_pantalla(): void
    {
        $this->acceso(['user_agent' => '<script>alert(1)</script>', 'user_name' => '<b>x</b>']);

        $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<b>x</b>', false);
    }

    // ================================================================ aislamiento entre iglesias

    public function test_un_admin_solo_ve_los_accesos_de_su_iglesia_y_el_super_admin_ve_todos(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $this->acceso(['user_name' => 'De Mi Iglesia']);
        $this->acceso(['user_name' => 'De Otra Iglesia', 'tenant_id' => $otra->id]);
        $this->acceso(['user_name' => 'Intruso Sin Iglesia', 'tenant_id' => null, 'user_id' => null, 'action' => AuditLog::ACCION_LOGIN_FALLIDO]);

        $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos'))
            ->assertSee('De Mi Iglesia')
            ->assertDontSee('De Otra Iglesia')
            ->assertDontSee('Intruso Sin Iglesia');

        $this->actingAs($this->crearSuperAdmin())->get(route('admin.auditoria.ingresos'))
            ->assertSee('De Mi Iglesia')
            ->assertSee('De Otra Iglesia')
            ->assertSee('Intruso Sin Iglesia');
    }

    // ================================================================ exportación CSV

    public function test_exporta_csv_con_bom_punto_y_coma_y_las_columnas_del_historial(): void
    {
        $this->acceso(['created_at' => '2026-10-09 14:35:20', 'user_name' => 'Ana Admin', 'ip_address' => '203.0.113.9']);

        $respuesta = $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos.exportar'));

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('historial_ingresos_', $respuesta->headers->get('Content-Disposition'));

        $csv = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lineas = preg_split('/\r\n/', trim(substr($csv, 3)));
        $this->assertSame('Fecha;Hora;Usuario;Correo;Evento;IP;Navegador;Sistema;"Agente de usuario"', $lineas[0]);
        $this->assertSame(
            '09/10/2026;14:35:20;"Ana Admin";ana@ibbla.test;"Inicio de sesión";203.0.113.9;"Chrome 118";Windows;"'.self::UA_CHROME_WINDOWS.'"',
            $lineas[1]
        );
    }

    public function test_el_csv_respeta_los_mismos_filtros_que_la_pantalla(): void
    {
        $this->acceso(['user_name' => 'Beto Tesorero']);
        $this->acceso(['user_name' => 'Carla Asistente']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.auditoria.ingresos.exportar', ['usuario' => 'carla']))
            ->streamedContent();

        $this->assertStringContainsString('Carla Asistente', $csv);
        $this->assertStringNotContainsString('Beto Tesorero', $csv);
    }

    public function test_el_csv_no_incluye_accesos_de_otra_iglesia(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $this->acceso(['user_name' => 'Ajeno', 'tenant_id' => $otra->id]);

        $csv = $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos.exportar'))->streamedContent();

        $this->assertStringNotContainsString('Ajeno', $csv);
    }

    public function test_el_csv_neutraliza_formulas_en_campos_controlados_por_quien_intenta_entrar(): void
    {
        $this->acceso([
            'user_name' => '=HYPERLINK("http://malo.example","clic")',
            'user_email' => '+cmd@malo.example',
            'user_agent' => '@SUM(1+1)',
        ]);

        $csv = $this->actingAs($this->admin)->get(route('admin.auditoria.ingresos.exportar'))->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK(", $csv);
        $this->assertStringContainsString("'+cmd@malo.example", $csv);
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        // Ninguna celda de datos arranca con = + - @ (ni entre comillas).
        $this->assertDoesNotMatchRegularExpression('/(^|;)"?[=+@-]/m', substr($csv, strpos($csv, "\r\n") + 2));
    }

    public function test_exportar_con_filtros_invalidos_vuelve_a_la_pantalla_en_vez_de_exportar_todo(): void
    {
        $this->acceso();

        $this->actingAs($this->admin)
            ->get(route('admin.auditoria.ingresos.exportar', ['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01']))
            ->assertRedirect(route('admin.auditoria.ingresos', ['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01']));
    }

    // ================================================================ permisos

    public function test_solo_el_admin_puede_ver_y_exportar_el_historial(): void
    {
        $this->acceso();

        foreach (['tesorero', 'asistente', 'miembro', 'servidor'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            $this->actingAs($usuario)->get(route('admin.auditoria.ingresos'))->assertForbidden();
            $this->actingAs($usuario)->get(route('admin.auditoria.ingresos.exportar'))->assertForbidden();
        }
    }

    public function test_un_visitante_sin_sesion_va_al_login(): void
    {
        $this->get(route('admin.auditoria.ingresos'))->assertRedirect('login');
        $this->get(route('admin.auditoria.ingresos.exportar'))->assertRedirect('login');
    }

    public function test_la_auditoria_general_enlaza_al_historial_de_ingresos(): void
    {
        $this->actingAs($this->admin)->get(route('admin.auditoria.index'))
            ->assertOk()
            ->assertSee(route('admin.auditoria.ingresos'), false);
    }
}
