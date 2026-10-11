<?php

namespace Tests\Unit;

use App\Support\NavegadorResumen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NavegadorResumenTest extends TestCase
{
    public static function userAgents(): array
    {
        return [
            'Chrome en Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36', 'Chrome 118 · Windows'],
            'Edge (dice Chrome y Safari)' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36 Edg/118.0.2088.46', 'Edge 118 · Windows'],
            'Opera' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36 OPR/88.0.4412.40', 'Opera 88 · Windows'],
            'Firefox en Linux' => ['Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/118.0', 'Firefox 118 · Linux'],
            'Chrome en Android' => ['Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Mobile Safari/537.36', 'Chrome 118 · Android'],
            'Samsung Internet' => ['Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/22.0 Chrome/111.0.0.0 Mobile Safari/537.36', 'Samsung Internet 22 · Android'],
            'Safari en iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'Safari 17 · iOS'],
            'Chrome en iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/118.0.5993.69 Mobile/15E148 Safari/604.1', 'Chrome 118 · iOS'],
            'Safari en Mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Safari/605.1.15', 'Safari 16 · macOS'],
            'Internet Explorer 11' => ['Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko', 'Internet Explorer 11 · Windows'],
            'App movil de la iglesia' => ['Mozilla/5.0 (Linux; Android 13) IBBSCApp/1.2 Chrome/118.0.0.0 Mobile', 'App móvil · Android'],
            'curl' => ['curl/8.4.0', 'curl 8'],
            'Postman' => ['PostmanRuntime/7.36.0', 'PostmanRuntime 7'],
            'Robot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Robot'],
            'Desconocido' => ['Algo/Raro 1.0', 'Otro navegador'],
            'Vacio' => ['', 'Desconocido'],
            'Nulo' => [null, 'Desconocido'],
        ];
    }

    #[DataProvider('userAgents')]
    public function test_resume_el_user_agent(?string $userAgent, string $esperado): void
    {
        $this->assertSame($esperado, NavegadorResumen::etiqueta($userAgent));
    }

    public function test_resumir_separa_navegador_y_sistema(): void
    {
        $this->assertSame(
            ['navegador' => 'Firefox 118', 'sistema' => 'Linux', 'etiqueta' => 'Firefox 118 · Linux'],
            NavegadorResumen::resumir('Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/118.0')
        );
    }
}
