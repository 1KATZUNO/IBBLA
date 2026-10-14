<?php

namespace App\Support\Pdf;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Plantilla unica de los PDF de reportes (HU-R14): carta, letra DejaVu Sans, encabezado con la
 * iglesia, el titulo, el periodo y los filtros aplicados; tablas que siguen en la pagina siguiente
 * repitiendo su encabezado; totales; y en cada pagina un pie con la fecha de generacion y
 * "Pagina N de M". Colores solo para estados (al dia / pendiente); nada decorativo.
 *
 * Dibuja sobre el canvas de DomPDF (LienzoPdf): ~150 filas en menos de un segundo, sin el riesgo
 * del 504 que tenia el Reporte General maquetado con Blade.
 *
 * Uso (un reporte nuevo del Centro de Reportes):
 *
 *     $doc = new DocumentoPdf('Cumplimiento de promesas', 'Corte al 12/10/2026', ['Personas: con promesa']);
 *     $doc->encabezado();
 *     $doc->nota('Le correspondía = lo prometido de enero a septiembre...');
 *     $doc->tarjetas([['etiqueta' => 'Con promesa', 'valor' => '166'], ['etiqueta' => 'Al día', 'valor' => '65']]);
 *     $doc->tituloSeccion('Pendientes (101)', DocumentoPdf::ROJO);
 *     $doc->tabla([
 *         ['titulo' => '#', 'ancho' => 0.8, 'alinear' => 'centro'],
 *         ['titulo' => 'Nombre', 'ancho' => 5],
 *         ['titulo' => 'Ha dado', 'ancho' => 2.5, 'alinear' => 'derecha'],
 *     ], $filas, total: ['', 'Total', '₡40,552,097'], colorEncabezado: DocumentoPdf::ROJO);
 *     return response($doc->salida(), 200, ['Content-Type' => 'application/pdf']);
 *
 * Cada celda es un texto o ['texto' => ..., 'color' => '#hex', 'negrita' => true, 'fondo' => '#hex'].
 * Los anchos de columna son relativos: se reparten en el ancho util de la pagina.
 */
class DocumentoPdf
{
    // Colores de la plantilla
    public const TINTA = '#1f2937';

    public const AZUL_OSCURO = '#1f3a5f';

    public const GRIS = '#4b5563';

    public const GRIS_CLARO = '#6b7280';

    public const BORDE = '#d1d5db';

    public const LINEA = '#e5e7eb';

    public const FONDO = '#f3f4f6';

    public const CEBRA = '#f9fafb';

    public const ROJO = '#b91c1c';

    public const VERDE = '#15803d';

    public const AMBAR = '#b45309';

    public const ROJO_SUAVE = '#fde2e1';

    public const VERDE_SUAVE = '#dcfce7';

    // Medidas (puntos)
    protected const MARGEN_X = 40.0;

    protected const MARGEN_ARRIBA = 40.0;

    protected const MARGEN_ABAJO = 46.0;

    protected const T_CUERPO = 8.0;

    protected const INTERLINEA = 1.25;

    public readonly LienzoPdf $lienzo;

    /** Posicion vertical del cursor: lo siguiente se dibuja desde aqui hacia abajo. */
    protected float $y;

    protected readonly CarbonImmutable $generadoEn;

    /** @var array{tenantNombre: string, tenantSiglas: string, tenantColor: string, tenantLogoBase64: string} */
    protected readonly array $iglesia;

    /**
     * @param  list<string>  $filtros  filtros aplicados, uno por texto ("Personas: con promesa")
     * @param  array<string, string>|null  $iglesia  salida de tenant_pdf_data() (por defecto la iglesia actual)
     */
    public function __construct(
        public readonly string $titulo,
        public readonly string $periodo = '',
        public readonly array $filtros = [],
        string $orientacion = 'portrait',
        ?array $iglesia = null,
        ?CarbonInterface $generadoEn = null,
    ) {
        $this->iglesia = $iglesia ?? tenant_pdf_data();
        $this->generadoEn = CarbonImmutable::instance($generadoEn ?? now());
        $this->lienzo = new LienzoPdf($orientacion, $titulo.' - '.$this->iglesia['tenantSiglas'], $this->iglesia['tenantNombre']);
        $this->y = self::MARGEN_ARRIBA;
    }

    // ------------------------------------------------------------------
    // Medidas utiles
    // ------------------------------------------------------------------

    public function x0(): float
    {
        return self::MARGEN_X;
    }

    public function x1(): float
    {
        return $this->lienzo->ancho - self::MARGEN_X;
    }

    public function anchoUtil(): float
    {
        return $this->x1() - $this->x0();
    }

    public function y(): float
    {
        return $this->y;
    }

    /** Hasta donde se puede dibujar en la pagina (arriba del pie). */
    public function limite(): float
    {
        return $this->lienzo->alto - self::MARGEN_ABAJO;
    }

    public function espacioLibre(): float
    {
        return $this->limite() - $this->y;
    }

    // ------------------------------------------------------------------
    // Paginas
    // ------------------------------------------------------------------

    /** Pagina nueva con el encabezado corrido (iglesia, titulo y periodo) arriba. */
    public function saltoDePagina(): static
    {
        $this->lienzo->nuevaPagina();
        $this->encabezadoCorrido();
        $this->y = self::MARGEN_ARRIBA;

        return $this;
    }

    /** Si lo que sigue ($alto) no cabe en lo que queda de la pagina, salta a la siguiente. */
    public function asegurarEspacio(float $alto): static
    {
        if ($this->y + $alto > $this->limite()) {
            $this->saltoDePagina();
        }

        return $this;
    }

    public function espacio(float $alto): static
    {
        $this->y += $alto;

        return $this;
    }

    /**
     * Cuantas paginas usaria $dibujo a partir de la posicion actual (1 si cabe en la que esta), sin
     * dibujar nada. Sirve para armar un indice antes de dibujar las secciones.
     */
    public function medirPaginas(callable $dibujo): int
    {
        $y = $this->y;
        try {
            return 1 + $this->lienzo->simular($dibujo);
        } finally {
            $this->y = $y;
        }
    }

    public function pagina(): int
    {
        return $this->lienzo->pagina();
    }

    // ------------------------------------------------------------------
    // Bloques
    // ------------------------------------------------------------------

    /**
     * Encabezado de la primera pagina: logo, nombre de la iglesia, titulo y una linea con el
     * periodo y los filtros aplicados.
     */
    public function encabezado(?string $titulo = null): static
    {
        $x = $this->x0();
        $logo = 34.0;
        $this->lienzo->imagenBase64($this->iglesia['tenantLogoBase64'] ?? '', $x, $this->y, $logo, $logo);
        $xTexto = $x + $logo + 10;
        $anchoTexto = $this->x1() - $xTexto;

        $this->lienzo->texto($this->iglesia['tenantSiglas'].' - '.$this->iglesia['tenantNombre'], $xTexto, $this->y + 9, LienzoPdf::REGULAR, 9.0, self::GRIS);
        $lineas = $this->lienzo->partirEnLineas($titulo ?? $this->titulo, LienzoPdf::NEGRITA, 15.0, $anchoTexto);
        $base = $this->y + 27;
        foreach ($lineas as $linea) {
            $this->lienzo->texto($linea, $xTexto, $base, LienzoPdf::NEGRITA, 15.0, self::AZUL_OSCURO);
            $base += 18;
        }

        // El periodo va en la linea de detalle, salvo que el titulo ya lo diga ("... - corte al 12/10/2026").
        $titulo ??= $this->titulo;
        $periodoAparte = $this->periodo !== '' && ! str_contains(mb_strtolower($titulo), mb_strtolower(preg_replace('/^Corte al /u', '', $this->periodo)));
        $detalle = implode('  ·  ', array_filter(array_merge($periodoAparte ? [$this->periodo] : [], $this->filtros)));
        $alto = max($logo, $base - 18 + 6 - $this->y);
        if ($detalle !== '') {
            foreach ($this->lienzo->partirEnLineas($detalle, LienzoPdf::REGULAR, 8.0, $anchoTexto) as $linea) {
                $this->lienzo->texto($linea, $xTexto, $base - 4, LienzoPdf::REGULAR, 8.0, self::GRIS_CLARO);
                $base += 10;
            }
            $alto = max($logo, $base - 10 + 4 - $this->y);
        }

        $this->y += $alto + 6;
        $this->lienzo->lineaHorizontal($this->x0(), $this->x1(), $this->y, self::AZUL_OSCURO, 1.2);
        $this->y += 10;

        return $this;
    }

    /** Parrafo de explicacion en gris, letra pequena. */
    public function nota(string $texto, float $tam = 7.5): static
    {
        return $this->parrafo($texto, $tam, self::GRIS);
    }

    /** Parrafo de texto corrido, partido en lineas; sigue en la pagina siguiente si hace falta. */
    public function parrafo(string $texto, float $tam = self::T_CUERPO, string $color = self::TINTA, string $estilo = LienzoPdf::REGULAR): static
    {
        $interlinea = $tam * self::INTERLINEA;
        foreach ($this->lienzo->partirEnLineas($texto, $estilo, $tam, $this->anchoUtil()) as $linea) {
            $this->asegurarEspacio($interlinea);
            $this->lienzo->texto($linea, $this->x0(), $this->y + $tam * 0.97, $estilo, $tam, $color);
            $this->y += $interlinea;
        }
        $this->y += 6;

        return $this;
    }

    /**
     * Fila de tarjetas de resumen: etiqueta arriba (fondo gris) y valor grande abajo.
     *
     * @param  list<array{etiqueta: string, valor: string, color?: string}>  $tarjetas
     */
    public function tarjetas(array $tarjetas, ?array $anchos = null): static
    {
        if ($tarjetas === []) {
            return $this;
        }

        $bordes = $this->bordesColumnas($anchos ?? array_fill(0, count($tarjetas), 1));
        $tamEtiqueta = 7.0;
        $lineasEtiqueta = 1;
        foreach ($tarjetas as $i => $tarjeta) {
            $ancho = $bordes[$i + 1] - $bordes[$i] - 8;
            $lineasEtiqueta = max($lineasEtiqueta, count($this->lienzo->partirEnLineas($tarjeta['etiqueta'], LienzoPdf::NEGRITA, $tamEtiqueta, $ancho)));
        }
        $altoEtiqueta = $lineasEtiqueta * $tamEtiqueta * 1.2 + 8;
        $altoValor = 22.0;

        $this->asegurarEspacio($altoEtiqueta + $altoValor + 4);
        $top = $this->y;
        $this->lienzo->rect($this->x0(), $top, $this->anchoUtil(), $altoEtiqueta, self::FONDO);

        foreach ($tarjetas as $i => $tarjeta) {
            $centro = ($bordes[$i] + $bordes[$i + 1]) / 2;
            $ancho = $bordes[$i + 1] - $bordes[$i] - 8;
            $lineas = $this->lienzo->partirEnLineas($tarjeta['etiqueta'], LienzoPdf::NEGRITA, $tamEtiqueta, $ancho);
            $base = $top + 4 + ($altoEtiqueta - 8 - count($lineas) * $tamEtiqueta * 1.2) / 2 + $tamEtiqueta * 0.97;
            foreach ($lineas as $linea) {
                $this->lienzo->textoCentrado($linea, $centro, $base, LienzoPdf::NEGRITA, $tamEtiqueta, self::GRIS);
                $base += $tamEtiqueta * 1.2;
            }
            $this->lienzo->textoCentrado($tarjeta['valor'], $centro, $top + $altoEtiqueta + 15, LienzoPdf::NEGRITA, 10.5, $tarjeta['color'] ?? self::TINTA);
            if ($i > 0) {
                $this->lienzo->lineaVertical($bordes[$i], $top, $top + $altoEtiqueta + $altoValor, self::LINEA, 0.4);
            }
        }
        $this->lienzo->lineaHorizontal($this->x0(), $this->x1(), $top + $altoEtiqueta, self::LINEA, 0.4);
        $this->lienzo->marco($this->x0(), $top, $this->anchoUtil(), $altoEtiqueta + $altoValor, self::BORDE, 0.6);
        $this->y = $top + $altoEtiqueta + $altoValor + 12;

        return $this;
    }

    /**
     * Titulo de una seccion. No queda huerfano al pie: si no caben el titulo, el encabezado de una
     * tabla y una fila, empieza en la pagina siguiente.
     */
    public function tituloSeccion(string $texto, string $color = self::AZUL_OSCURO, float $reservar = 60.0): static
    {
        $this->asegurarEspacio(20 + $reservar);
        $this->y += 4;
        $this->lienzo->texto($texto, $this->x0(), $this->y + 11, LienzoPdf::NEGRITA, 11.0, $color);
        $this->y += 18;

        return $this;
    }

    /**
     * Tabla con encabezado de color, filas en cebra y fila de total opcional. Si no cabe, sigue en
     * la pagina siguiente repitiendo el encabezado. Los textos largos se parten en lineas.
     *
     * @param  list<array{titulo: string, ancho?: float, alinear?: string}>  $columnas  alinear: izquierda|derecha|centro
     * @param  iterable<list<string|array{texto: string, color?: string, negrita?: bool, fondo?: string}>>  $filas
     * @param  list<string|array>|null  $total
     */
    public function tabla(array $columnas, iterable $filas, ?array $total = null, string $colorEncabezado = self::AZUL_OSCURO, float $tam = self::T_CUERPO, bool $cebra = true): static
    {
        $bordes = $this->bordesColumnas(array_map(fn ($c) => (float) ($c['ancho'] ?? 1), $columnas));
        $encabezado = array_map(fn ($c) => ['texto' => $c['titulo'], 'negrita' => true, 'color' => '#ffffff'], $columnas);
        // Los titulos de columna siempre pueden ir en dos lineas ("Le / correspondia").
        $columnasEncabezado = array_map(fn ($c) => ['ajustar' => true] + $c, $columnas);
        $tamEncabezado = $tam - 0.5;

        $altoEncabezado = $this->altoFila($encabezado, $columnasEncabezado, $bordes, $tamEncabezado, 4.0);
        // El encabezado nunca queda solo al pie de la pagina.
        $this->asegurarEspacio($altoEncabezado + $tam * 3);
        $this->filaTabla($encabezado, $columnasEncabezado, $bordes, $tamEncabezado, 4.0, $colorEncabezado);

        $n = 0;
        foreach ($filas as $fila) {
            $alto = $this->altoFila($fila, $columnas, $bordes, $tam, 3.0);
            if ($this->y + $alto > $this->limite()) {
                $this->saltoDePagina();
                $this->filaTabla($encabezado, $columnasEncabezado, $bordes, $tamEncabezado, 4.0, $colorEncabezado);
            }
            $this->filaTabla($fila, $columnas, $bordes, $tam, 3.0, $cebra && $n % 2 === 1 ? self::CEBRA : null);
            $n++;
        }

        if ($total !== null) {
            $celdas = array_map(fn ($c) => is_array($c) ? $c + ['negrita' => true] : ['texto' => (string) $c, 'negrita' => true], $total);
            $alto = $this->altoFila($celdas, $columnas, $bordes, $tam, 4.0);
            if ($this->y + $alto > $this->limite()) {
                $this->saltoDePagina();
                $this->filaTabla($encabezado, $columnasEncabezado, $bordes, $tamEncabezado, 4.0, $colorEncabezado);
            }
            $this->filaTabla($celdas, $columnas, $bordes, $tam, 4.0, self::FONDO);
        }

        $this->y += 10;

        return $this;
    }

    /**
     * Indice: una linea por entrada con el texto, puntos guia y el numero de pagina a la derecha.
     *
     * @param  list<array{0: string, 1: int|string}>  $entradas
     */
    public function indice(array $entradas, float $tam = 9.0): static
    {
        $interlinea = $tam * 1.6;
        $anchoPagina = $this->lienzo->anchoTexto('0000', LienzoPdf::REGULAR, $tam);
        foreach ($entradas as [$texto, $pagina]) {
            $this->asegurarEspacio($interlinea);
            $base = $this->y + $tam * 0.97;
            $texto = $this->recortar((string) $texto, LienzoPdf::REGULAR, $tam, $this->anchoUtil() - $anchoPagina - 20);
            $this->lienzo->texto($texto, $this->x0(), $base, LienzoPdf::REGULAR, $tam, self::TINTA);
            $this->lienzo->textoDerecha((string) $pagina, $this->x1(), $base, LienzoPdf::REGULAR, $tam, self::TINTA);
            $desde = $this->x0() + $this->lienzo->anchoTexto($texto, LienzoPdf::REGULAR, $tam) + 6;
            $hasta = $this->x1() - $this->lienzo->anchoTexto((string) $pagina, LienzoPdf::REGULAR, $tam) - 6;
            for ($x = $desde; $x < $hasta; $x += 4) {
                $this->lienzo->rect($x, $base - 1, 0.8, 0.8, self::BORDE);
            }
            $this->y += $interlinea;
        }

        return $this;
    }

    /**
     * Portada corta: logo, nombre de la iglesia, titulo grande y lineas de detalle centradas.
     *
     * @param  list<string>  $lineas
     */
    public function portada(array $lineas = []): static
    {
        $centro = ($this->x0() + $this->x1()) / 2;
        $y = $this->lienzo->alto * 0.28;
        $this->lienzo->imagenBase64($this->iglesia['tenantLogoBase64'] ?? '', $centro - 32, $y - 80, 64, 64);
        $this->lienzo->textoCentrado($this->iglesia['tenantSiglas'].' - '.$this->iglesia['tenantNombre'], $centro, $y + 6, LienzoPdf::REGULAR, 11.0, self::GRIS);
        $y += 34;
        foreach ($this->lienzo->partirEnLineas($this->titulo, LienzoPdf::NEGRITA, 24.0, $this->anchoUtil()) as $linea) {
            $this->lienzo->textoCentrado($linea, $centro, $y, LienzoPdf::NEGRITA, 24.0, self::AZUL_OSCURO);
            $y += 30;
        }
        $this->lienzo->lineaHorizontal($centro - 60, $centro + 60, $y - 10, self::AZUL_OSCURO, 1.2);
        $y += 14;
        foreach (array_filter(array_merge([$this->periodo], $this->filtros, $lineas)) as $linea) {
            foreach ($this->lienzo->partirEnLineas($linea, LienzoPdf::REGULAR, 11.0, $this->anchoUtil()) as $parte) {
                $this->lienzo->textoCentrado($parte, $centro, $y, LienzoPdf::REGULAR, 11.0, self::TINTA);
                $y += 16;
            }
        }
        $this->lienzo->textoCentrado('Generado el '.$this->fechaGeneracion(), $centro, $y + 10, LienzoPdf::REGULAR, 9.0, self::GRIS_CLARO);
        $this->y = $y + 24;

        return $this;
    }

    /**
     * Cierra el documento: pie en todas las paginas y devuelve el PDF.
     */
    public function salida(): string
    {
        $texto = $this->iglesia['tenantSiglas'].' - '.$this->titulo
            .($this->periodo !== '' ? ' - '.$this->periodo : '')
            .' - generado el '.$this->fechaGeneracion();
        $base = $this->lienzo->alto - 24;
        $texto = $this->recortar($texto, LienzoPdf::REGULAR, 6.5, $this->anchoUtil() - 70);

        $this->lienzo->enCadaPagina(function (int $pagina, int $total) use ($texto, $base) {
            $this->lienzo->lineaHorizontal($this->x0(), $this->x1(), $base - 10, self::LINEA, 0.5);
            $this->lienzo->texto($texto, $this->x0(), $base, LienzoPdf::REGULAR, 6.5, self::GRIS_CLARO);
            $this->lienzo->textoDerecha('Página '.$pagina.' de '.$total, $this->x1(), $base, LienzoPdf::REGULAR, 6.5, self::GRIS_CLARO);
        });

        return $this->lienzo->salida();
    }

    public function fechaGeneracion(): string
    {
        return $this->generadoEn->format('d/m/Y h:i A');
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /** Linea fina arriba de las paginas que siguen a la primera: iglesia y titulo a la izquierda, periodo a la derecha. */
    protected function encabezadoCorrido(): void
    {
        $base = 26.0;
        $izquierda = $this->iglesia['tenantSiglas'].' · '.$this->titulo;
        $this->lienzo->texto($this->recortar($izquierda, LienzoPdf::REGULAR, 6.5, $this->anchoUtil() * 0.65), $this->x0(), $base, LienzoPdf::REGULAR, 6.5, self::GRIS_CLARO);
        if ($this->periodo !== '') {
            $this->lienzo->textoDerecha($this->periodo, $this->x1(), $base, LienzoPdf::REGULAR, 6.5, self::GRIS_CLARO);
        }
        $this->lienzo->lineaHorizontal($this->x0(), $this->x1(), $base + 4, self::LINEA, 0.5);
    }

    /**
     * Bordes de las columnas repartiendo el ancho util segun los pesos.
     *
     * @param  list<float>  $pesos
     * @return list<float>
     */
    protected function bordesColumnas(array $pesos): array
    {
        $suma = array_sum($pesos) ?: 1;
        $bordes = [$this->x0()];
        $x = $this->x0();
        foreach ($pesos as $peso) {
            $x += $this->anchoUtil() * $peso / $suma;
            $bordes[] = $x;
        }
        $bordes[count($bordes) - 1] = $this->x1();

        return $bordes;
    }

    /**
     * @param  list<string|array>  $celdas
     * @param  list<array<string, mixed>>  $columnas
     */
    protected function altoFila(array $celdas, array $columnas, array $bordes, float $tam, float $relleno): float
    {
        $lineas = 1;
        foreach (array_values($celdas) as $i => $celda) {
            $lineas = max($lineas, count($this->lineasCelda($this->celda($celda), $columnas[$i] ?? [], $bordes[$i + 1] - $bordes[$i] - 8, $tam)[0]));
        }

        return $lineas * $tam * self::INTERLINEA + 2 * $relleno;
    }

    /**
     * Lineas de una celda y el tamano de letra con que se dibujan. Las columnas de texto
     * (alineadas a la izquierda) se parten en varias lineas; las de numeros (derecha o centro)
     * van en una sola y, si no caben, con letra un poco mas chica (nunca un numero partido).
     * Se cambia con 'ajustar' => true|false en la columna.
     *
     * @param  array{texto: string, negrita: bool}  $celda
     * @param  array<string, mixed>  $columna
     * @return array{0: list<string>, 1: float}
     */
    protected function lineasCelda(array $celda, array $columna, float $ancho, float $tam): array
    {
        $estilo = $celda['negrita'] ? LienzoPdf::NEGRITA : LienzoPdf::REGULAR;
        $ajustar = $columna['ajustar'] ?? ! in_array($columna['alinear'] ?? 'izquierda', ['derecha', 'centro'], true);

        if ($ajustar) {
            return [$this->lienzo->partirEnLineas($celda['texto'], $estilo, $tam, $ancho), $tam];
        }

        $medida = $this->lienzo->anchoTexto($celda['texto'], $estilo, $tam);
        if ($medida > $ancho && $medida > 0) {
            $tam = max(5.0, floor($tam * $ancho / $medida * 10) / 10);
        }

        return [[$celda['texto']], $tam];
    }

    /**
     * @param  list<string|array>  $celdas
     * @param  list<array<string, mixed>>  $columnas
     */
    protected function filaTabla(array $celdas, array $columnas, array $bordes, float $tam, float $relleno, ?string $fondo): void
    {
        $alto = $this->altoFila($celdas, $columnas, $bordes, $tam, $relleno);
        if ($fondo !== null) {
            $this->lienzo->rect($this->x0(), $this->y, $this->anchoUtil(), $alto, $fondo);
        }

        $interlinea = $tam * self::INTERLINEA;
        foreach (array_values($celdas) as $i => $celda) {
            $c = $this->celda($celda);
            $izq = $bordes[$i] + 4;
            $der = $bordes[$i + 1] - 4;
            if (isset($c['fondo'])) {
                $this->lienzo->rect($bordes[$i], $this->y, $bordes[$i + 1] - $bordes[$i], $alto, $c['fondo']);
            }
            $estilo = $c['negrita'] ? LienzoPdf::NEGRITA : LienzoPdf::REGULAR;
            [$lineas, $tamCelda] = $this->lineasCelda($c, $columnas[$i] ?? [], $der - $izq, $tam);
            // Con letra achicada la linea base baja un poco para que el texto siga centrado.
            $base = $this->y + $relleno + ($alto - 2 * $relleno - count($lineas) * $interlinea) / 2 + $tam * 0.97 - ($tam - $tamCelda) * 0.4;
            $alinear = $columnas[$i]['alinear'] ?? 'izquierda';
            foreach ($lineas as $linea) {
                match ($alinear) {
                    'derecha' => $this->lienzo->textoDerecha($linea, $der, $base, $estilo, $tamCelda, $c['color']),
                    'centro' => $this->lienzo->textoCentrado($linea, ($izq + $der) / 2, $base, $estilo, $tamCelda, $c['color']),
                    default => $this->lienzo->texto($linea, $izq, $base, $estilo, $tamCelda, $c['color']),
                };
                $base += $interlinea;
            }
        }

        $this->lienzo->lineaHorizontal($this->x0(), $this->x1(), $this->y + $alto, self::LINEA, 0.4);
        $this->y += $alto;
    }

    /** @return array{texto: string, color: string, negrita: bool, fondo?: string} */
    protected function celda(string|int|float|array|null $celda): array
    {
        if (! is_array($celda)) {
            return ['texto' => (string) $celda, 'color' => self::TINTA, 'negrita' => false];
        }

        return ['texto' => (string) ($celda['texto'] ?? ''), 'color' => $celda['color'] ?? self::TINTA, 'negrita' => (bool) ($celda['negrita'] ?? false)]
            + (isset($celda['fondo']) ? ['fondo' => $celda['fondo']] : []);
    }

    /** Recorta con "…" lo que no cabe en una sola linea. */
    protected function recortar(string $texto, string $estilo, float $tam, float $ancho): string
    {
        if ($this->lienzo->anchoTexto($texto, $estilo, $tam) <= $ancho) {
            return $texto;
        }
        while (mb_strlen($texto) > 1 && $this->lienzo->anchoTexto($texto.'…', $estilo, $tam) > $ancho) {
            $texto = mb_substr($texto, 0, -1);
        }

        return rtrim($texto).'…';
    }
}
