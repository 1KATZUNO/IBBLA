<?php

namespace Tests\Feature;

use App\Support\Manual\Manual;
use Tests\TestCase;

/**
 * El manual no debe quedarse atras de la app: no explica lo que ya no existe, explica lo que si existe y
 * los botones que cita llevan el mismo texto que la pantalla. (Sin base de datos.)
 */
class ManualContenidoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Manual::olvidar();
    }

    private function textoDelManual(?string $salvoSlug = null): string
    {
        $texto = '';
        foreach (Manual::paginas() as $pagina) {
            if ($pagina->slug !== $salvoSlug) {
                $texto .= "\n".$pagina->cuerpo;
            }
        }

        return mb_strtolower($texto);
    }

    public function test_el_manual_no_explica_funciones_que_ya_no_existen(): void
    {
        // La pagina de novedades si puede decir que se quitaron
        $texto = $this->textoDelManual('novedades-noviembre-2026');

        foreach (['entrar como invitado', 'crear cuenta', 'registrarse', 'regístrate', 'guardar sobre'] as $obsoleto) {
            $this->assertStringNotContainsString($obsoleto, $texto, "El manual todavia explica «{$obsoleto}», que ya no existe");
        }
        $this->assertFalse(file_exists(resource_path('views/auth/register.blade.php')), 'El registro publico se elimino');
    }

    public function test_el_manual_explica_lo_nuevo_de_la_e1(): void
    {
        $texto = $this->textoDelManual();

        foreach ([
            'centro de reportes', 'cumplimiento de promesas', 'fecha de corte', 'estados de cuenta en lote', 'selector de personas',
            'accesos de edición', 'alta rápida', 'sobre anónimo', 'solo anónimos', 'asignar persona',
            'callmebot', 'guardar y siguiente', 'avisos de whatsapp',
            // Fase 2
            'reporte de cultos', 'reporte de ingresos', 'periodo anterior', 'seleccionar todos los filtrados', 'vista previa',
            'último aporte a sus compromisos', 'mes en curso', 'sin persona', 'confirmar', 'persona vinculada', 'planificador',
            'solo lectura', 'corrección de culto cerrado',
        ] as $tema) {
            $this->assertStringContainsString($tema, $texto, "Al manual le falta explicar «{$tema}»");
        }
    }

    public function test_la_pagina_de_novedades_es_la_primera_del_indice(): void
    {
        $paginas = Manual::paginas();

        $this->assertSame('novedades-noviembre-2026', $paginas[0]->slug);
        $this->assertSame('Novedades de noviembre 2026', $paginas[0]->titulo);
        $this->assertSame('primeros-pasos', $paginas[1]->slug);
    }

    public function test_los_botones_que_cita_la_pagina_de_recuento_existen_con_ese_texto_en_las_pantallas(): void
    {
        $vistas = '';
        foreach (glob(resource_path('views/recuento/{,partials/}*.blade.php'), GLOB_BRACE) as $archivo) {
            $vistas .= file_get_contents($archivo);
        }
        $pagina = Manual::pagina('recuento')->cuerpo;

        foreach ([
            '+ Agregar sobre', '+ Dinero suelto', 'Cerrar culto', 'Guardar y siguiente', 'Guardar y terminar', 'Todos los cultos',
            'Solo anónimos', 'Asignar persona', 'Solo transferencias', 'Recuento completo', 'Guardar firmas', 'Agregar tesorero',
            '+ Nueva persona', 'Guardar cambios', 'Sobre anónimo (sin persona)',
        ] as $boton) {
            $this->assertStringContainsString($boton, $vistas, "La pantalla ya no dice «{$boton}»");
            $this->assertStringContainsString($boton, $pagina, "La pagina de Recuento no menciona «{$boton}»");
        }
    }

    public function test_cada_pagina_cita_solo_capturas_que_existen_y_cada_captura_numerada_tiene_su_descripcion(): void
    {
        $config = json_decode(file_get_contents(base_path('tools/manual/capturas.json')), true);
        $archivos = array_column($config['capturas'], 'archivo');

        foreach (Manual::paginas() as $pagina) {
            preg_match_all('/\(img:([^\s)]+\.jpg)/', $pagina->cuerpo, $m);
            foreach ($m[1] as $imagen) {
                $this->assertFileExists(Manual::directorioImagenes().'/'.$imagen, "{$pagina->slug}: falta la captura {$imagen}");
                $this->assertContains($imagen, $archivos, "{$pagina->slug}: {$imagen} no esta en tools/manual/capturas.json (no se puede regenerar)");
            }
        }
    }
}
