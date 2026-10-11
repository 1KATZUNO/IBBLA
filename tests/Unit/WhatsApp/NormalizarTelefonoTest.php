<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\WhatsAppService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Normalización de teléfonos al formato de wa.me (E.164 sin «+»).
 * Es una función pura: no necesita la aplicación ni la base de datos.
 */
class NormalizarTelefonoTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function telefonosValidos(): array
    {
        return [
            '8 dígitos' => ['88887777', '50688887777'],
            'con guion' => ['8888-7777', '50688887777'],
            'con espacio' => ['8888 7777', '50688887777'],
            'espacios alrededor' => ['  8888 7777  ', '50688887777'],
            'con puntos' => ['8888.7777', '50688887777'],
            'espacio duro (copiado de Word)' => ["8888\u{00A0}7777", '50688887777'],
            'guion largo (copiado de Word)' => ["8888\u{2013}7777", '50688887777'],
            'fijo (empieza en 2)' => ['2222-3333', '50622223333'],
            'empieza en 5' => ['5888-7777', '50658887777'],
            '+506 con espacio y guion' => ['+506 8888-7777', '50688887777'],
            '+506 pegado' => ['+50688887777', '50688887777'],
            '506 sin más' => ['506 8888 7777', '50688887777'],
            '506 pegado' => ['50688887777', '50688887777'],
            '506 entre paréntesis' => ['(506) 8888-7777', '50688887777'],
            '+ con paréntesis' => ['+(506) 8888-7777', '50688887777'],
            'prefijo 00' => ['00506 8888 7777', '50688887777'],
            'otro país: Nicaragua' => ['+505 8888 7777', '50588887777'],
            'otro país: EE. UU.' => ['+1 (305) 555-1234', '13055551234'],
            'otro país con 00' => ['001 305 555 1234', '13055551234'],
        ];
    }

    /** @return array<string, array{0: ?string}> */
    public static function telefonosInvalidos(): array
    {
        return [
            'null' => [null],
            'vacío' => [''],
            'solo espacios' => ['   '],
            'texto' => ['no tiene'],
            '7 dígitos' => ['8888-777'],
            '9 dígitos' => ['8888-77777'],
            'empieza en 1' => ['1234-5678'],
            'empieza en 9' => ['9999-9999'],
            'empieza en 0' => ['0888-7777'],
            '506 con 7 dígitos' => ['+506 888 7777'],
            '506 con 9 dígitos' => ['+506 88887777 7'],
            '506 con número que empieza en 1' => ['+506 1888 7777'],
            '506 sin + y con 10 dígitos' => ['5068888777'],
            'dos números con barra' => ['8888-7777 / 2222-3333'],
            'número con extensión' => ['8888-7777 ext 2'],
            'letras mezcladas' => ['Tel: 8888-7777'],
            'doble +' => ['++50688887777'],
            '+ solo' => ['+'],
            'internacional muy corto' => ['+12345'],
            'internacional que empieza en 0' => ['+0123456789'],
            'internacional de más de 15 dígitos' => ['+1234567890123456'],
            '00 con solo 8 dígitos' => ['0088887777'],
        ];
    }

    #[DataProvider('telefonosValidos')]
    public function test_normaliza_telefonos_validos(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, WhatsAppService::normalizarTelefono($entrada));
    }

    #[DataProvider('telefonosInvalidos')]
    public function test_telefonos_invalidos_dan_null(?string $entrada): void
    {
        $this->assertNull(WhatsAppService::normalizarTelefono($entrada));
    }

    public function test_formatea_el_telefono_para_mostrarlo(): void
    {
        $this->assertSame('+506 8888-7777', WhatsAppService::formatearTelefono('50688887777'));
        $this->assertSame('+13055551234', WhatsAppService::formatearTelefono('13055551234'));
        $this->assertSame('', WhatsAppService::formatearTelefono(null));
        $this->assertSame('', WhatsAppService::formatearTelefono(''));
    }
}
