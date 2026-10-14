<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\EnlaceCallMeBot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lo que la persona pega en su perfil: el enlace (o el mensaje completo) que le responde el bot.
 * Se leen el número y la clave; nunca se llama a la dirección pegada y solo vale si es de callmebot.com.
 */
class EnlaceCallMeBotTest extends TestCase
{
    private const ENLACE = 'https://api.callmebot.com/whatsapp.php?phone=+50688887777&text=This+is+a+test&apikey=1234567';

    public function test_lee_el_numero_y_la_clave_del_enlace_del_bot(): void
    {
        $e = EnlaceCallMeBot::analizar(self::ENLACE);

        $this->assertNotNull($e);
        $this->assertSame('1234567', $e->apikey);
        $this->assertSame('50688887777', $e->telefono);
        $this->assertTrue($e->traeTelefono);
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function telefonos(): array
    {
        return [
            'con + (parse_str lo vuelve espacio)' => ['phone=+50688887777', '50688887777'],
            '+ codificado' => ['phone=%2B50688887777', '50688887777'],
            'sin +' => ['phone=50688887777', '50688887777'],
            'local de 8 dígitos' => ['phone=88887777', '50688887777'],
            'otro país' => ['phone=+34123123123', '34123123123'],
            'con espacios codificados' => ['phone=%2B506%208888%207777', '50688887777'],
        ];
    }

    #[DataProvider('telefonos')]
    public function test_normaliza_el_numero_del_enlace(string $phone, ?string $esperado): void
    {
        $e = EnlaceCallMeBot::analizar("https://api.callmebot.com/whatsapp.php?{$phone}&text=Hola&apikey=999888");

        $this->assertSame($esperado, $e?->telefono, $phone);
    }

    public function test_un_numero_que_no_sirve_se_avisa_aparte(): void
    {
        $e = EnlaceCallMeBot::analizar('https://api.callmebot.com/whatsapp.php?phone=12&text=Hola&apikey=999888');

        $this->assertNotNull($e);
        $this->assertTrue($e->traeTelefono);
        $this->assertNull($e->telefono);
    }

    public function test_un_enlace_sin_numero_tambien_sirve_pero_no_hay_numero_que_comparar(): void
    {
        $e = EnlaceCallMeBot::analizar('https://api.callmebot.com/whatsapp.php?text=Hola&apikey=999888');

        $this->assertSame('999888', $e?->apikey);
        $this->assertNull($e->telefono);
        $this->assertFalse($e->traeTelefono);
    }

    public function test_acepta_el_mensaje_completo_del_bot(): void
    {
        $mensaje = "API Activated for your phone number. Your APIKEY is 1234567\n\nYou can now send messages using the API:\n"
            .self::ENLACE."\n\nFor more info visit https://www.callmebot.com";

        $e = EnlaceCallMeBot::analizar($mensaje);

        $this->assertSame('1234567', $e?->apikey);
        $this->assertSame('50688887777', $e->telefono);
    }

    /** @return array<string, array{0: string}> */
    public static function pegadosConRuido(): array
    {
        return [
            'entre paréntesis y con punto' => ['('.self::ENLACE.').'],
            'entre comillas' => ['"'.self::ENLACE.'"'],
            'con signos de WhatsApp' => ['*'.self::ENLACE.'*'],
            'con espacios y saltos' => ["  \n ".self::ENLACE." \n\n"],
            'con &amp; de HTML' => [str_replace('&', '&amp;', self::ENLACE)],
            'con caracteres invisibles' => ["\u{200B}".self::ENLACE."\u{FEFF}"],
            'en mayúsculas el sitio' => [str_replace('api.callmebot.com', 'API.CALLMEBOT.COM', self::ENLACE)],
            'con http' => [str_replace('https://', 'http://', self::ENLACE)],
            'subdominio de callmebot' => [str_replace('api.callmebot.com', 'www.callmebot.com', self::ENLACE)],
        ];
    }

    #[DataProvider('pegadosConRuido')]
    public function test_tolera_lo_que_se_pega_al_copiar(string $pegado): void
    {
        $e = EnlaceCallMeBot::analizar($pegado);

        $this->assertSame('1234567', $e?->apikey, $pegado);
        $this->assertSame('50688887777', $e->telefono, $pegado);
    }

    public function test_sin_enlace_se_acepta_la_frase_de_la_clave_sin_numero(): void
    {
        foreach (['Your APIKEY is 1234567', 'API Activated for your phone number. Your APIKEY is 1234567', 'apikey: 998877'] as $texto) {
            $e = EnlaceCallMeBot::analizar($texto);

            $this->assertNotNull($e, $texto);
            $this->assertNull($e->telefono);
            $this->assertFalse($e->traeTelefono);
        }

        $this->assertSame('1234567', EnlaceCallMeBot::analizar('Your APIKEY is 1234567')->apikey);
    }

    /** @return array<string, array{0: string}> */
    public static function noValen(): array
    {
        return [
            'vacío' => [''],
            'solo espacios' => ["  \n "],
            'texto sin enlace ni clave' => ['hola, ya activé el bot'],
            'un número suelto (parece teléfono, no clave)' => ['88887777'],
            'otro sitio' => ['https://www.ejemplo.com/whatsapp.php?phone=+50688887777&apikey=1234567'],
            'un sitio que se parece' => ['https://evilcallmebot.com/whatsapp.php?phone=+50688887777&apikey=1234567'],
            'callmebot.com dentro de otro dominio' => ['https://callmebot.com.malo.net/whatsapp.php?phone=+50688887777&apikey=1234567'],
            'callmebot solo en la ruta' => ['https://malo.net/callmebot.com/whatsapp.php?phone=+50688887777&apikey=1234567'],
            'enlace de otro sitio que dice apikey' => ['https://malo.net/x?apikey=ABC123 Your APIKEY is 998877'],
            'sin apikey' => ['https://api.callmebot.com/whatsapp.php?phone=+50688887777&text=Hola'],
            'apikey vacía' => ['https://api.callmebot.com/whatsapp.php?phone=+50688887777&apikey='],
            'apikey demasiado corta' => ['https://api.callmebot.com/whatsapp.php?phone=+50688887777&apikey=12'],
            'apikey con caracteres raros' => ['https://api.callmebot.com/whatsapp.php?phone=+50688887777&apikey=12<script>'],
            'apikey demasiado larga' => ['https://api.callmebot.com/whatsapp.php?apikey='.'a'.str_repeat('b', 80)],
            'esquema raro' => ['javascript://api.callmebot.com/whatsapp.php?apikey=1234567'],
        ];
    }

    #[DataProvider('noValen')]
    public function test_lo_que_no_es_un_enlace_de_callmebot_se_rechaza(string $pegado): void
    {
        $this->assertNull(EnlaceCallMeBot::analizar($pegado), $pegado);
    }

    public function test_si_hay_varios_enlaces_se_usa_el_de_callmebot(): void
    {
        $e = EnlaceCallMeBot::analizar('https://malo.net/x?apikey=ABC123 y luego '.self::ENLACE);

        $this->assertSame('1234567', $e?->apikey);
    }

    public function test_el_diagnostico_dice_por_que_no_sirve(): void
    {
        $this->assertSame(EnlaceCallMeBot::VACIO, EnlaceCallMeBot::diagnostico(null));
        $this->assertSame(EnlaceCallMeBot::VACIO, EnlaceCallMeBot::diagnostico('   '));
        $this->assertSame(EnlaceCallMeBot::SIN_ENLACE, EnlaceCallMeBot::diagnostico('ya activé el bot'));
        $this->assertSame(EnlaceCallMeBot::OTRO_SITIO, EnlaceCallMeBot::diagnostico('https://www.ejemplo.com/x?apikey=1234567'));
        $this->assertSame(EnlaceCallMeBot::SIN_CLAVE, EnlaceCallMeBot::diagnostico('https://api.callmebot.com/whatsapp.php?phone=+50688887777'));
    }

    public function test_analizar_con_null_devuelve_null(): void
    {
        $this->assertNull(EnlaceCallMeBot::analizar(null));
    }
}
